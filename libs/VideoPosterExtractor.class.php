<?php

require_once __DIR__ . '/utils.funcs.php';
require_once __DIR__ . '/imageproc.class.php';
require_once __DIR__ . '/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

/**
 * Extracts a poster frame from a video and uploads it to R2.
 *
 * Uses ffmpeg scene change detection to find an interesting (non-black)
 * frame in a single pass. Works with both local files and presigned
 * HTTP URLs (ffmpeg fetches only the bytes it needs).
 */
class VideoPosterExtractor
{
  private string $ffmpegPath = '/usr/local/bin/ffmpeg';
  private array $awsConfig;
  private UsersImages $usersImages;

  /** Minimum mean brightness (fraction of quantum) to consider a frame non-black. */
  const BLACK_THRESHOLD = 0.04;

  /** Maximum candidate frames to extract. */
  const MAX_FRAMES = 5;

  /** ffmpeg timeout in seconds. */
  const TIMEOUT = 30;

  public function __construct(array $awsConfig, UsersImages $usersImages)
  {
    $this->awsConfig = $awsConfig;
    $this->usersImages = $usersImages;
  }

  /**
   * Extract a poster frame from a video and upload it to R2.
   *
   * Never throws — all errors are logged and false is returned.
   * Poster extraction failure must never break an upload.
   *
   * @param string $videoInput     Local file path OR presigned HTTP URL
   * @param string $videoFilename  The video's filename in storage (e.g. "abc123.mp4")
   * @param int    $fileId         Database ID of the video record in users_images
   * @param string $userNpub       User's npub for R2 metadata
   * @return bool True on success, false on failure
   */
  public function extractAndUpload(
    string $videoInput,
    string $videoFilename,
    int $fileId,
    string $userNpub
  ): bool {
    $tempDir = sys_get_temp_dir();
    $tempPrefix = generateUniqueFilename('poster_', $tempDir);
    $candidatePattern = $tempPrefix . '_%02d.jpg';
    $candidateFiles = [];

    try {
      // Step 1: Extract candidate frames using scene change detection
      $candidateFiles = $this->extractCandidateFrames($videoInput, $candidatePattern);

      if (empty($candidateFiles)) {
        error_log("VideoPosterExtractor: ffmpeg produced no frames for: " . substr($videoFilename, 0, 20));
        return false;
      }

      // Step 2: Pick the best (non-black) frame
      $bestFrame = $this->pickBestFrame($candidateFiles);

      // Step 3: Optimize with ImageProcessor and get dimensions
      $imageProcessor = new ImageProcessor($bestFrame);
      $imageProcessor->save();
      $dimensions = $imageProcessor->getImageDimensions();
      $imageProcessor->optimiseImage();

      // Step 4: Upload to R2
      $sha256 = hash_file('sha256', $bestFrame);
      $objectKey = "{$videoFilename}/poster.jpg";
      $bucketSuffix = SiteConfig::getBucketSuffix('professional_account_video');
      $bucket = $this->awsConfig['r2']['bucket'] . $bucketSuffix;

      $uploaded = storeToR2Bucket(
        sourceFilePath: $bestFrame,
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
        error_log("VideoPosterExtractor: R2 upload failed for: {$objectKey}");
        return false;
      }

      // Step 5: Update DB with poster dimensions
      try {
        $this->usersImages->update($fileId, [
          'media_width' => $dimensions['width'],
          'media_height' => $dimensions['height'],
        ]);
      } catch (\Throwable $e) {
        // Poster is already uploaded, dimension update failure is non-critical
        error_log("VideoPosterExtractor: DB update failed for ID {$fileId}: " . $e->getMessage());
      }

      error_log("VideoPosterExtractor: poster extracted and uploaded for {$videoFilename}");
      return true;
    } catch (\Throwable $e) {
      error_log("VideoPosterExtractor: " . $e->getMessage());
      return false;
    } finally {
      // Clean up all candidate frame temp files
      foreach ($candidateFiles as $file) {
        if (file_exists($file)) {
          @unlink($file);
        }
      }
    }
  }

  /**
   * Extract candidate frames using ffmpeg scene change detection.
   *
   * Uses a single-pass select filter: grabs frames where scene change
   * exceeds the threshold, plus the first frame as a guaranteed fallback.
   *
   * @return string[] Paths to extracted JPEG files (may be empty on failure)
   */
  private function extractCandidateFrames(string $videoInput, string $outputPattern): array
  {
    $inputEsc = escapeshellarg($videoInput);
    $outputEsc = escapeshellarg($outputPattern);

    $command = sprintf(
      'timeout %d nice -n 19 %s -y -hide_banner -i %s'
      . " -vf \"select='gt(scene\\,0.01)+eq(n\\,0)'\""
      . ' -vsync vfr -frames:v %d -q:v 2 -f image2 %s 2>&1',
      self::TIMEOUT,
      escapeshellarg($this->ffmpegPath),
      $inputEsc,
      self::MAX_FRAMES,
      $outputEsc
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
      error_log("VideoPosterExtractor: ffmpeg failed (exit {$returnVar}): " . implode("\n", array_slice($output, -5)));
      // Fall back: try a simple single-frame extraction at 1 second
      return $this->extractFallbackFrame($videoInput, $outputPattern);
    }

    // Collect the output files (ffmpeg uses %02d: _01.jpg, _02.jpg, ...)
    $files = [];
    for ($i = 1; $i <= self::MAX_FRAMES; $i++) {
      $path = sprintf($outputPattern, $i);
      if (file_exists($path) && filesize($path) > 0) {
        $files[] = $path;
      } else {
        break;
      }
    }

    return $files;
  }

  /**
   * Fallback: extract a single frame at 1 second without scene detection.
   *
   * Used when the scene-change filter fails (e.g., codec incompatibility).
   *
   * @return string[] Array with single file path, or empty on failure
   */
  private function extractFallbackFrame(string $videoInput, string $outputPattern): array
  {
    $singleOutput = sprintf($outputPattern, 1);
    $inputEsc = escapeshellarg($videoInput);
    $outputEsc = escapeshellarg($singleOutput);

    $command = sprintf(
      'timeout %d nice -n 19 %s -y -hide_banner -ss 1 -i %s -vframes 1 -q:v 2 -f image2 %s 2>&1',
      self::TIMEOUT,
      escapeshellarg($this->ffmpegPath),
      $inputEsc,
      $outputEsc
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0 || !file_exists($singleOutput) || filesize($singleOutput) === 0) {
      error_log("VideoPosterExtractor: fallback extraction also failed (exit {$returnVar})");
      return [];
    }

    return [$singleOutput];
  }

  /**
   * Pick the best frame from candidates by checking brightness.
   *
   * Returns the first non-black frame, or the brightest one if all are dark.
   *
   * @param string[] $files Paths to candidate JPEG files
   * @return string Path to the best frame
   */
  private function pickBestFrame(array $files): string
  {
    if (count($files) === 1) {
      return $files[0];
    }

    $bestFile = $files[0];
    $bestBrightness = 0.0;

    foreach ($files as $file) {
      $brightness = $this->getFrameBrightness($file);

      if ($brightness >= self::BLACK_THRESHOLD) {
        // First non-black frame wins
        return $file;
      }

      if ($brightness > $bestBrightness) {
        $bestBrightness = $brightness;
        $bestFile = $file;
      }
    }

    return $bestFile;
  }

  /**
   * Get the mean brightness of a JPEG as a fraction (0.0 = black, 1.0 = white).
   */
  private function getFrameBrightness(string $filePath): float
  {
    try {
      $img = new \Imagick($filePath);
      $stats = $img->getImageChannelMean(\Imagick::CHANNEL_ALL);
      $brightness = $stats['mean'] / \Imagick::getQuantum();
      $img->destroy();
      return $brightness;
    } catch (\Throwable $e) {
      error_log("VideoPosterExtractor: brightness check failed: " . $e->getMessage());
      return 0.0;
    }
  }
}
