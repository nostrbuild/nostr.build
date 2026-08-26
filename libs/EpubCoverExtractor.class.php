<?php

require_once __DIR__ . '/utils.funcs.php';
require_once __DIR__ . '/imageproc.class.php';
require_once __DIR__ . '/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

/**
 * Extracts the cover image from an EPUB and uploads it to R2, mirroring
 * VideoPosterExtractor's persistence convention (poster lands beside the
 * file, at "<filename>/poster.jpg", in the same tier bucket).
 *
 * Cover lookup follows the EPUB OCF spec: META-INF/container.xml points at
 * the OPF rootfile; the cover is then resolved via EITHER the EPUB3
 * convention (a manifest <item> with properties="cover-image") OR the
 * EPUB2 convention (<meta name="cover" content="ID"/> in <metadata>,
 * pointing at a manifest item's id).
 */
class EpubCoverExtractor
{
  /** Skip covers larger than this, a malformed or hostile EPUB should
   *  never balloon memory/CPU on the upload path. */
  const MAX_COVER_BYTES = 10 * 1024 * 1024;

  /** Cap on the small XML entries we read in full (container.xml, the OPF).
   *  A crafted EPUB can declare a multi-GB compressible XML entry; reading
   *  it uncapped via getFromName() decompresses fully into memory before
   *  we ever look at it, which can hard-OOM the PHP process (a fatal, not
   *  a \Throwable, so no catch block downstream can save us from it). */
  const MAX_XML_BYTES = 1 * 1024 * 1024;

  /** Cap on the SVG-wrapper cover entry. Standard Ebooks (and other
   *  publishers using the same convention) declare the cover as an SVG
   *  document that embeds the real raster image as a base64 data URI, so
   *  this entry is the base64-inflated raster plus SVG markup, larger than
   *  MAX_XML_BYTES allows but still bounded. Same statIndex-before-read cap
   *  discipline as MAX_XML_BYTES, just a bigger ceiling. */
  const MAX_SVG_BYTES = 12 * 1024 * 1024;

  /** Manifest media-types accepted for a cover image. Anything else
   *  (including SVG) is rejected before its bytes ever reach Imagick. SVG
   *  is handled separately, as a wrapper to unwrap, never as bytes handed
   *  to Imagick directly. */
  const ALLOWED_MEDIA_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  /** data: URI prefixes recognized inside an SVG wrapper's <image> element,
   *  mapped to the sniffed format sniffImageFormat() must confirm. */
  const SVG_DATA_URI_PATTERN = '/data:image\/(jpeg|png|webp|gif);base64,([A-Za-z0-9+\/=]+)/i';

  private array $awsConfig;

  public function __construct(array $awsConfig)
  {
    $this->awsConfig = $awsConfig;
  }

  /**
   * Extract the raw cover image bytes from a local .epub file.
   *
   * Never throws, returns null on any failure (malformed zip, missing
   * container.xml/OPF, no resolvable cover, oversized entry, disallowed or
   * unrecognized image format). Extraction failure must never break the
   * upload; the caller treats null as "no cover available" and moves on.
   *
   * @return string|null Raw image bytes, or null when no cover was found.
   */
  public static function extractCoverBytes(string $epubPath): ?string
  {
    $zip = null;
    $opened = false;
    try {
      $zip = new \ZipArchive();
      if ($zip->open($epubPath) !== true) {
        return null;
      }
      $opened = true;

      $containerXml = self::readZipEntryCapped($zip, 'META-INF/container.xml', self::MAX_XML_BYTES);
      if ($containerXml === null) {
        return null;
      }

      $opfPath = self::findOpfPath($containerXml);
      if ($opfPath === null) {
        return null;
      }

      $opfXml = self::readZipEntryCapped($zip, $opfPath, self::MAX_XML_BYTES);
      if ($opfXml === null) {
        return null;
      }

      $cover = self::findCover($opfXml);
      if ($cover === null) {
        return null;
      }
      [$coverHref, $coverMediaType] = $cover;

      $opfDir = dirname($opfPath);
      $opfDir = ($opfDir === '.' || $opfDir === '/') ? '' : $opfDir . '/';

      // Standard Ebooks (and other publishers using the same convention)
      // declare the cover manifest item as image/svg+xml: an SVG wrapper
      // whose single <image> embeds the real raster as a base64 data URI
      // (or, less commonly, references a sibling raster file by relative
      // href). Unwrap it as text; the SVG document itself is NEVER handed
      // to Imagick, only the raster bytes found inside it, and those still
      // go through the exact same sniff gate as every other path here.
      if ($coverMediaType === 'image/svg+xml' || preg_match('/\.svg$/i', $coverHref) === 1) {
        return self::extractFromSvgCover($zip, $opfDir, $coverHref);
      }

      // Reject anything else the manifest didn't declare as one of the
      // four raster formats we're willing to hand to Imagick, BEFORE we
      // even look at the bytes.
      if (!in_array($coverMediaType, self::ALLOWED_MEDIA_TYPES, true)) {
        return null;
      }

      $index = self::locateEntry($zip, $opfDir, $coverHref);
      if ($index === false) {
        return null;
      }

      $stat = $zip->statIndex($index);
      if (!is_array($stat) || (int) $stat['size'] <= 0 || (int) $stat['size'] > self::MAX_COVER_BYTES) {
        return null;
      }

      $bytes = $zip->getFromIndex($index);
      if (!is_string($bytes) || $bytes === '') {
        return null;
      }

      // Sniff the actual bytes against the four allowed signatures,
      // independent of whatever the manifest claimed. A mismatch (or a
      // format we don't recognize at all) aborts extraction rather than
      // passing untrusted bytes through to Imagick on a false premise.
      if (self::sniffImageFormat($bytes) === null) {
        return null;
      }

      return $bytes;
    } catch (\Throwable $e) {
      error_log('EpubCoverExtractor: extraction failed: ' . $e->getMessage());
      return null;
    } finally {
      if ($opened && $zip instanceof \ZipArchive) {
        @$zip->close();
      }
    }
  }

  /**
   * Read one zip entry in full, but only after confirming its uncompressed
   * size is within $maxBytes via statIndex(); never decompresses an
   * oversized entry just to find out it's oversized.
   */
  private static function readZipEntryCapped(\ZipArchive $zip, string $name, int $maxBytes): ?string
  {
    $index = $zip->locateName($name);
    if ($index === false) {
      return null;
    }

    $stat = $zip->statIndex($index);
    if (!is_array($stat) || (int) $stat['size'] <= 0 || (int) $stat['size'] > $maxBytes) {
      return null;
    }

    $bytes = $zip->getFromIndex($index);
    if (!is_string($bytes) || $bytes === '') {
      return null;
    }

    return $bytes;
  }

  /**
   * Resolve a (possibly relative, possibly percent-encoded) href against a
   * base directory into a zip entry index, trying the normalized path
   * first and falling back to the bare decoded href for malformed hrefs.
   * Returns false when neither resolves to a real entry.
   */
  private static function locateEntry(\ZipArchive $zip, string $baseDir, string $href): int|false
  {
    $decodedHref = rawurldecode($href);
    $entryName = self::normalizeZipPath($baseDir . $decodedHref);

    $index = $zip->locateName($entryName);
    if ($index === false) {
      $index = $zip->locateName($decodedHref);
    }
    return $index;
  }

  /**
   * Unwrap an SVG-wrapper cover (the Standard Ebooks convention): read the
   * SVG as text, capped at MAX_SVG_BYTES via the same statIndex-before-read
   * discipline used everywhere else, then pull the real raster out of it.
   * Tries, in order:
   *   1. A data:image/(jpeg|png|webp|gif);base64,... URI on an
   *      xlink:href/href attribute (the common case: the raster is
   *      embedded directly in the SVG).
   *   2. A relative href pointing at a sibling raster file, resolved
   *      against the SVG entry's own directory.
   * Either way, the resulting bytes go through the same sniff gate as
   * every other path (self::sniffImageFormat()); the SVG's own markup is
   * never passed to Imagick, and neither is anything that fails the sniff.
   */
  private static function extractFromSvgCover(\ZipArchive $zip, string $opfDir, string $svgHref): ?string
  {
    $svgIndex = self::locateEntry($zip, $opfDir, $svgHref);
    if ($svgIndex === false) {
      return null;
    }

    $stat = $zip->statIndex($svgIndex);
    if (!is_array($stat) || (int) $stat['size'] <= 0 || (int) $stat['size'] > self::MAX_SVG_BYTES) {
      return null;
    }

    $svgText = $zip->getFromIndex($svgIndex);
    if (!is_string($svgText) || $svgText === '') {
      return null;
    }

    // Case 1: embedded base64 data URI.
    if (preg_match(self::SVG_DATA_URI_PATTERN, $svgText, $m) === 1) {
      $decoded = base64_decode($m[2], true);
      if ($decoded === false || $decoded === '' || strlen($decoded) > self::MAX_COVER_BYTES) {
        return null;
      }
      return self::sniffImageFormat($decoded) !== null ? $decoded : null;
    }

    // Case 2: a relative href to a sibling raster file, resolved against
    // the SVG's own directory (not the OPF's), since that is what the
    // href inside the SVG document is relative to.
    if (preg_match_all('/(?:xlink:href|href)\s*=\s*"([^"]+)"/i', $svgText, $mAll) > 0) {
      $svgStatName = $stat['name'] ?? '';
      $svgDir = dirname((string) $svgStatName);
      $svgDir = ($svgDir === '.' || $svgDir === '/') ? '' : $svgDir . '/';

      foreach ($mAll[1] as $candidateHref) {
        if ($candidateHref === '' || str_starts_with($candidateHref, 'data:') || str_starts_with($candidateHref, '#')) {
          continue;
        }
        if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $candidateHref) !== 1) {
          continue;
        }

        $rasterIndex = self::locateEntry($zip, $svgDir, $candidateHref);
        if ($rasterIndex === false) {
          continue;
        }

        $rasterStat = $zip->statIndex($rasterIndex);
        if (!is_array($rasterStat) || (int) $rasterStat['size'] <= 0 || (int) $rasterStat['size'] > self::MAX_COVER_BYTES) {
          continue;
        }

        $rasterBytes = $zip->getFromIndex($rasterIndex);
        if (!is_string($rasterBytes) || $rasterBytes === '') {
          continue;
        }

        if (self::sniffImageFormat($rasterBytes) !== null) {
          return $rasterBytes;
        }
      }
    }

    return null;
  }

  /**
   * Check the first bytes of a string against the magic numbers of the
   * four raster formats we accept. Returns the format name or null.
   */
  private static function sniffImageFormat(string $bytes): ?string
  {
    if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
      return 'jpeg';
    }
    if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
      return 'png';
    }
    if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
      return 'gif';
    }
    if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
      return 'webp';
    }
    return null;
  }

  /**
   * Resolve the OPF rootfile path from container.xml's <rootfile full-path=...>.
   */
  private static function findOpfPath(string $containerXml): ?string
  {
    $container = @simplexml_load_string($containerXml);
    if ($container === false) {
      return null;
    }
    $container->registerXPathNamespace('c', 'urn:oasis:names:tc:opendocument:xmlns:container');
    $rootfiles = $container->xpath('//c:rootfile[@full-path]');
    if (empty($rootfiles)) {
      return null;
    }
    $opfPath = (string) $rootfiles[0]['full-path'];
    return $opfPath !== '' ? $opfPath : null;
  }

  /**
   * Resolve the cover image's manifest href + declared media-type from the
   * OPF XML, trying the EPUB3 convention first, then falling back to EPUB2.
   *
   * @return array{0: string, 1: string}|null [href, media-type], or null.
   */
  private static function findCover(string $opfXml): ?array
  {
    $opf = @simplexml_load_string($opfXml);
    if ($opf === false) {
      return null;
    }

    // Namespace-agnostic lookups: OPF always uses a default (unprefixed)
    // namespace, so local-name() matching sidesteps registering it.
    $manifestItems = $opf->xpath('//*[local-name()="manifest"]/*[local-name()="item"]');
    if (empty($manifestItems)) {
      return null;
    }

    $idToItem = [];
    foreach ($manifestItems as $item) {
      $attrs = $item->attributes();
      $id = (string) $attrs['id'];
      $href = (string) $attrs['href'];
      $mediaType = (string) $attrs['media-type'];
      $properties = (string) $attrs['properties'];
      if ($id !== '' && $href !== '') {
        $idToItem[$id] = [$href, $mediaType];
      }
      // EPUB3 convention: manifest item flagged as the cover image.
      if ($href !== '' && preg_match('/(^|\s)cover-image(\s|$)/', $properties) === 1) {
        return [$href, $mediaType];
      }
    }

    // EPUB2 convention: <meta name="cover" content="ID"/> in <metadata>,
    // where ID names a manifest item.
    $metas = $opf->xpath('//*[local-name()="metadata"]/*[local-name()="meta"]');
    foreach ($metas as $meta) {
      $attrs = $meta->attributes();
      if ((string) $attrs['name'] === 'cover') {
        $coverId = (string) $attrs['content'];
        if ($coverId !== '' && isset($idToItem[$coverId])) {
          return $idToItem[$coverId];
        }
        break;
      }
    }

    return null;
  }

  /**
   * Collapse "./" and "../" segments in a zip entry path built by joining
   * the OPF directory with a (possibly relative) href.
   */
  private static function normalizeZipPath(string $path): string
  {
    $stack = [];
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.') {
        continue;
      }
      if ($part === '..') {
        array_pop($stack);
        continue;
      }
      $stack[] = $part;
    }
    return implode('/', $stack);
  }

  /**
   * Flatten an alpha channel onto a white background, in place. A
   * transparent-PNG cover would otherwise survive imageproc's
   * convertToJpeg() unchanged (it deliberately skips transparent PNGs) and
   * get stored as PNG bytes under a ".../poster.jpg" key, a content-type
   * lie. No-op (and never throws) when the image has no alpha channel or
   * flattening fails for any reason; the caller's convertToJpeg() then
   * runs on the (possibly still-transparent) source as a safe fallback.
   */
  private static function flattenAlphaToWhite(string $filePath): void
  {
    $imagick = null;
    $flattened = null;
    try {
      $imagick = new \Imagick($filePath);
      if (!$imagick->getImageAlphaChannel()) {
        return;
      }
      $imagick->setImageBackgroundColor(new \ImagickPixel('white'));
      $flattened = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
      $flattened->setImageFormat('jpeg');
      $flattened->writeImage($filePath);
    } catch (\Throwable $e) {
      error_log('EpubCoverExtractor: alpha flatten failed: ' . $e->getMessage());
    } finally {
      if ($flattened instanceof \Imagick) {
        $flattened->clear();
        $flattened->destroy();
      }
      if ($imagick instanceof \Imagick) {
        $imagick->clear();
        $imagick->destroy();
      }
    }
  }

  /**
   * Extract the cover from an EPUB and upload it to R2, mirroring
   * VideoPosterExtractor::extractAndUpload's persistence convention: the
   * cover lands beside the document at "<filename>/poster.jpg" in the
   * professional_account_document bucket (documents are Professional+
   * only, there is no free-tier document bucket to branch on).
   *
   * Never throws, all errors are logged and false is returned. Cover
   * extraction failure must never break the upload.
   *
   * @param string $epubPath         Local temp file path of the uploaded EPUB
   * @param string $documentFilename The document's filename in storage (e.g. "abc123.epub")
   * @param string $userNpub         User's npub for R2 metadata ('' for npub-less email accounts)
   * @return bool True on success, false when no cover was found or upload failed
   */
  public function extractAndUpload(string $epubPath, string $documentFilename, string $userNpub): bool
  {
    $tempFile = null;
    try {
      $coverBytes = self::extractCoverBytes($epubPath);
      if ($coverBytes === null) {
        return false;
      }

      $tempFile = tempnam(sys_get_temp_dir(), 'epubcover_');
      if ($tempFile === false || file_put_contents($tempFile, $coverBytes) === false) {
        error_log('EpubCoverExtractor: failed to write cover bytes to temp file');
        return false;
      }

      // The destination key is always ".../poster.jpg" (mirrors the video
      // poster convention), so the extracted cover, whatever format the
      // EPUB embedded it in, must be normalized to JPEG. Flatten any alpha
      // channel onto white FIRST: convertToJpeg() intentionally leaves a
      // transparent PNG untouched, which would otherwise store PNG bytes
      // under the ".jpg" key.
      self::flattenAlphaToWhite($tempFile);

      $imageProcessor = new ImageProcessor($tempFile);
      $imageProcessor->fixImageOrientation();
      $imageProcessor->convertToJpeg();
      $imageProcessor->save();
      $imageProcessor->optimiseImage();

      $sha256 = hash_file('sha256', $tempFile);
      $objectKey = "{$documentFilename}/poster.jpg";
      $bucketSuffix = SiteConfig::getBucketSuffix('professional_account_document');
      $bucket = $this->awsConfig['r2']['bucket'] . $bucketSuffix;

      $uploaded = storeToR2Bucket(
        sourceFilePath: $tempFile,
        destinationKey: $objectKey,
        destinationBucket: $bucket,
        endPoint: $this->awsConfig['r2']['endpoint'],
        accessKey: $this->awsConfig['r2']['credentials']['key'],
        secretKey: $this->awsConfig['r2']['credentials']['secret'],
        metadata: [
          'sha256' => $sha256,
          'npub' => $userNpub,
        ],
      );

      if (!$uploaded) {
        error_log("EpubCoverExtractor: R2 upload failed for: {$objectKey}");
        return false;
      }

      error_log("EpubCoverExtractor: cover extracted and uploaded for {$documentFilename}");
      return true;
    } catch (\Throwable $e) {
      error_log('EpubCoverExtractor: ' . $e->getMessage());
      return false;
    } finally {
      if ($tempFile !== null && file_exists($tempFile)) {
        @unlink($tempFile);
      }
    }
  }
}
