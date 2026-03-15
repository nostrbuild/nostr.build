<?php

require_once __DIR__ . '/../imageproc.class.php';
require_once __DIR__ . '/../GifConverter.class.php';
require_once __DIR__ . '/../VideoRepackager.class.php';

/**
 * MediaProcessor handles all image and video processing for uploads.
 *
 * Extracted from MultimediaUpload to consolidate the free, pro, and profile
 * image processing pipelines plus video repackaging into a single class.
 */
class MediaProcessor
{
  /** GIF files larger than this are candidates for downsizing (2 MB). */
  const GIF_SIZE_THRESHOLD = 2097152;

  /** Downsized GIF must be at least 5% smaller to be worth keeping. */
  const GIF_SAVINGS_THRESHOLD = 0.95;

  /** Videos under this size are eligible for repackaging (200 MB). */
  const VIDEO_REPACKAGE_LIMIT = 209715200;

  /** Maximum dimension (width or height) for free-tier images. */
  const FREE_MAX_DIMENSION = 3840;

  /** JPEG quality for free-tier images. */
  const FREE_QUALITY = 75;

  /** Dimension for profile picture crop/resize. */
  const PROFILE_DIMENSION = 256;

  /** JPEG quality for profile pictures. */
  const PROFILE_QUALITY = 75;

  private GifConverter $gifConverter;

  public function __construct(GifConverter $gifConverter)
  {
    $this->gifConverter = $gifConverter;
  }

  /**
   * Process an image according to the given tier.
   *
   * Processing chains per tier:
   *   free:    downsizeGifIfNeeded -> convertHeicToJpeg -> convertToJpeg ->
   *            fixImageOrientation -> resizeImage(3840) -> reduceQuality(75) ->
   *            stripImageMetadata -> save -> optimiseImage
   *   pro:     downsizeGifIfNeeded -> convertHeicToJpeg -> convertTiffToJpeg ->
   *            fixImageOrientation -> stripImageMetadata -> save -> optimiseImage
   *   profile: (static) fixImageOrientation -> cropSquare -> resizeImage(256) ->
   *            reduceQuality(75) -> stripImageMetadata -> save -> optimiseImage
   *            (animated/video) convertToGif -> stripImageMetadata -> save -> optimiseImage
   *
   * @param string $tmpPath   Absolute path to the temporary file
   * @param array  $fileType  File type array with 'type' and 'extension' keys
   * @param string $tier      One of 'free', 'pro', or 'profile'
   * @param bool   $noTransform Whether transformations are disabled
   * @param int    $fileSize  File size in bytes
   * @return array{
   *   metadata: array,
   *   dimensions: array,
   *   blurhash: string,
   *   privateMetadata: array,
   *   newTmpPath: ?string,
   *   noTransformOverride: bool
   * }
   */
  public function processImage(string $tmpPath, array $fileType, string $tier, bool $noTransform, int $fileSize): array
  {
    $newTmpPath = null;
    $noTransformOverride = false;

    switch ($tier) {
      case 'free':
        return $this->processFree($tmpPath, $fileType, $noTransform, $fileSize);

      case 'pro':
        return $this->processPro($tmpPath, $fileType, $noTransform, $fileSize);

      case 'profile':
        return $this->processProfile($tmpPath, $fileType);

      default:
        throw new \InvalidArgumentException("Unknown processing tier: {$tier}");
    }
  }

  /**
   * Repackage a video file if it is under the size limit and transformations are allowed.
   *
   * @param string $tmpPath     Absolute path to the temporary video file
   * @param int    $fileSize    File size in bytes
   * @param bool   $noTransform Whether transformations are disabled
   * @return string|null New temporary path if the video was repackaged, null otherwise
   */
  public function processVideo(string $tmpPath, int $fileSize, bool $noTransform): ?string
  {
    if ($fileSize >= self::VIDEO_REPACKAGE_LIMIT || $noTransform) {
      return null;
    }

    try {
      $videoRepackager = new VideoRepackager($tmpPath);
      $repackagedTmp = $videoRepackager->repackageVideo();
      if (is_string($repackagedTmp) && $repackagedTmp !== '') {
        return $repackagedTmp;
      }
    } catch (\Exception $e) {
      error_log("Video repackaging failed: " . $e->getMessage());
    }

    return null;
  }

  /**
   * Attempt to downsize an animated GIF if it exceeds the size threshold.
   *
   * This is the unified GIF logic previously duplicated in both the free and
   * pro processing methods. If the downsized version is not at least 5% smaller
   * than the original, the original is kept and further transformations are
   * disabled via noTransformOverride.
   *
   * @param string $tmpPath     Absolute path to the temporary file
   * @param array  $fileType    File type array with 'type' and 'extension' keys
   * @param int    $fileSize    File size in bytes
   * @param bool   $noTransform Whether transformations are already disabled
   * @return array{newTmpPath: ?string, noTransformOverride: bool}
   */
  private function downsizeGifIfNeeded(string $tmpPath, array $fileType, int $fileSize, bool $noTransform): array
  {
    if ($noTransform) {
      return ['newTmpPath' => null, 'noTransformOverride' => false];
    }

    if ($fileType['type'] !== 'image' || !in_array($fileType['extension'], ['gif'])) {
      return ['newTmpPath' => null, 'noTransformOverride' => false];
    }

    $img = new ImageProcessor($tmpPath);
    if (!$img->isAnimated()) {
      return ['newTmpPath' => null, 'noTransformOverride' => false];
    }

    if ($fileSize <= self::GIF_SIZE_THRESHOLD) {
      return ['newTmpPath' => null, 'noTransformOverride' => false];
    }

    // Attempt to downsize the GIF
    $tmpGif = $this->gifConverter->downsizeGif($tmpPath);

    // Check if the optimized version is at least 5% smaller
    if (filesize($tmpGif) < self::GIF_SAVINGS_THRESHOLD * filesize($tmpPath)) {
      return ['newTmpPath' => $tmpGif, 'noTransformOverride' => false];
    }

    // Optimized GIF is not meaningfully smaller — keep the original
    error_log('Optimized GIF is not smaller, keeping original: ' . filesize($tmpGif) . ' vs ' . filesize($tmpPath));

    if (file_exists($tmpGif)) {
      unlink($tmpGif);
    }

    return ['newTmpPath' => null, 'noTransformOverride' => true];
  }

  /**
   * Process a free-tier image upload.
   *
   * Chain: downsizeGifIfNeeded -> convertHeicToJpeg -> convertToJpeg ->
   *        fixImageOrientation -> resizeImage(3840) -> reduceQuality(75) ->
   *        stripImageMetadata -> save -> optimiseImage
   *
   * @param string $tmpPath     Absolute path to the temporary file
   * @param array  $fileType    File type array
   * @param bool   $noTransform Whether transformations are disabled
   * @param int    $fileSize    File size in bytes
   * @return array Processed image data
   */
  private function processFree(string $tmpPath, array $fileType, bool $noTransform, int $fileSize): array
  {
    $gifResult = $this->downsizeGifIfNeeded($tmpPath, $fileType, $fileSize, $noTransform);
    $newTmpPath = $gifResult['newTmpPath'];
    $noTransformOverride = $gifResult['noTransformOverride'];

    $effectivePath = $newTmpPath ?? $tmpPath;
    $effectiveNoTransform = $noTransform || $noTransformOverride;

    $img = new ImageProcessor($effectivePath);

    if (!$effectiveNoTransform) {
      $img->convertHeicToJpeg()
        ->convertToJpeg()
        ->fixImageOrientation()
        ->resizeImage(self::FREE_MAX_DIMENSION, self::FREE_MAX_DIMENSION)
        ->reduceQuality(self::FREE_QUALITY)
        ->stripImageMetadata()
        ->save();
      $img->optimiseImage();
    } else {
      error_log('Skipping image transformations for Free upload, no_transform is set: ' . ($effectiveNoTransform ? 'true' : 'false') . PHP_EOL);
    }

    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
      'privateMetadata' => $img->getPrivateMetadata() ?? [],
      'newTmpPath' => $newTmpPath,
      'noTransformOverride' => $noTransformOverride,
    ];
  }

  /**
   * Process a pro-tier image upload.
   *
   * Chain: downsizeGifIfNeeded -> convertHeicToJpeg -> convertTiffToJpeg ->
   *        fixImageOrientation -> stripImageMetadata -> save -> optimiseImage
   *
   * @param string $tmpPath     Absolute path to the temporary file
   * @param array  $fileType    File type array
   * @param bool   $noTransform Whether transformations are disabled
   * @param int    $fileSize    File size in bytes
   * @return array Processed image data
   */
  private function processPro(string $tmpPath, array $fileType, bool $noTransform, int $fileSize): array
  {
    $gifResult = $this->downsizeGifIfNeeded($tmpPath, $fileType, $fileSize, $noTransform);
    $newTmpPath = $gifResult['newTmpPath'];
    $noTransformOverride = $gifResult['noTransformOverride'];

    $effectivePath = $newTmpPath ?? $tmpPath;
    $effectiveNoTransform = $noTransform || $noTransformOverride;

    $img = new ImageProcessor($effectivePath);

    if (!$effectiveNoTransform) {
      $img->convertHeicToJpeg()
        ->convertTiffToJpeg()
        ->fixImageOrientation()
        ->stripImageMetadata()
        ->save();
      $img->optimiseImage();
    } else {
      error_log('Skipping image transformations for Pro upload, no_transform is set: ' . ($effectiveNoTransform ? 'true' : 'false') . PHP_EOL);
    }

    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
      'privateMetadata' => $img->getPrivateMetadata() ?? [],
      'newTmpPath' => $newTmpPath,
      'noTransformOverride' => $noTransformOverride,
    ];
  }

  /**
   * Process a profile picture upload.
   *
   * Static images:   fixImageOrientation -> cropSquare -> resizeImage(256) ->
   *                  reduceQuality(75) -> stripImageMetadata -> save -> optimiseImage
   * Animated/video:  convertToGif -> stripImageMetadata -> save -> optimiseImage
   *
   * @param string $tmpPath  Absolute path to the temporary file
   * @param array  $fileType File type array
   * @return array Processed image data
   */
  private function processProfile(string $tmpPath, array $fileType): array
  {
    $newTmpPath = null;
    $img = null;

    // Determine if the file is an animated GIF or video
    $isAnimatedOrVideo = (
      ($fileType['type'] === 'image' && in_array($fileType['extension'], ['gif'])) ||
      $fileType['type'] === 'video'
    );

    if ($isAnimatedOrVideo) {
      // Convert animated image or video to GIF via GifConverter
      $tmpGif = $this->gifConverter->convertToGif($tmpPath);
      $newTmpPath = $tmpGif;
      $effectivePath = $tmpGif;
    } else {
      // Process static image
      $effectivePath = $tmpPath;
      $img = new ImageProcessor($effectivePath);
      $img->fixImageOrientation()
        ->cropSquare()
        ->resizeImage(self::PROFILE_DIMENSION, self::PROFILE_DIMENSION)
        ->reduceQuality(self::PROFILE_QUALITY);
    }

    // Common processing steps for both static and animated profile images
    $img = $img ?? new ImageProcessor($effectivePath);
    $img->stripImageMetadata()
      ->save();
    $img->optimiseImage();

    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
      'privateMetadata' => [],
      'newTmpPath' => $newTmpPath,
      'noTransformOverride' => false,
    ];
  }
}
