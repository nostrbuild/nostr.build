<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;
use kornrunner\Blurhash\Blurhash;

// Define array for image extensions
$imageExtensions = [
  "jpg",
  "jpeg",
  "png",
  "gif",
  "bmp",
  "tiff",
  "webp",
  "heic", // HEIF image is not supported by many OSes
  "jfif"
];

// Define array for audio/video extensions
$avExtensions = [
  "avi",
  "mp4",
  "mov",
  "mp3",
  "wav",
  "flv",
  "wmv",
  "webm",
  "aac",
  "flac",
  "ogg"
];

// Define array for animated extensions
$animatedExtensions = [
  "gif",    // Animated GIF
  "webp",   // Animated WebP
  "flif",   // Free Lossless Image Format, can support animation
  "mng",    // Multiple-image Network Graphics, essentially an animated PNG
  "apng",   // Animated Portable Network Graphics
];

/***
 * Sample usage:
 * 
$imageProcessor = new ImageProcessor($imagePath);
$imageProcessor->fixImageOrientation()
    ->cropSquare() // Optinally crop square for profile pictures
    ->resizeImage(1024, 1024)
    ->reduceQuality(75)
    ->stripImageMetadata()
    ->save() // Save after using Imagick functions and before optimizing
    ->optimiseImage();

$metadata = $imageProcessor->getImageMetadata();
$imageblurhash = $imageProcessor->calculateBlurhash();
 */
/**
 * Summary of ImageProcessor
 */
class ImageProcessor
{
  /**
   * Summary of heifConverter
   * @var string
   */
  private $heifConverter = "/usr/bin/heif-convert";
  /**
   * Summary of imagick
   * @var 
   */
  private $imagick;
  /**
   * Summary of isSaved
   * @var 
   */
  private $isSaved = false;
  /**
   * Summary of imagePath
   * @var 
   */
  private $imagePath;
  /**
   * Summary of supportedFormats
   * @var 
   */
  private static $supportedFormats;
  /**
   * Summary of isOversize
   * @var bool indicates if the image is oversize
   */
  private $isOversize = false;

  /**
   * Summary of __construct
   * @param mixed $imagePath
   * @throws \InvalidArgumentException
   */
  public function __construct($imagePath)
  {
    if (!file_exists($imagePath)) {
      throw new InvalidArgumentException("File does not exist: $imagePath");
    }

    $this->imagePath = $imagePath;
    $this->imagick = new Imagick(realpath($imagePath));
    $width = $this->imagick->getImageWidth();
    $height = $this->imagick->getImageHeight();
    $this->isOversize = $width > 4096 || $height > 4096;

    if (!self::isSupported($this->imagick->getImageFormat())) {
      throw new InvalidArgumentException("Image format not supported: {$this->imagick->getImageFormat()}");
    }
  }

  /**
   * Summary of isSupported
   * @param mixed $format
   * @return bool
   */
  public static function isSupported($format): bool
  {
    if (self::$supportedFormats === null) {
      self::$supportedFormats = Imagick::queryFormats();
    }
    return in_array(strtoupper($format), self::$supportedFormats);
  }

  /**
   * Summary of reduceQuality
   * @param mixed $quality
   * @return ImageProcessor
   */
  public function reduceQuality($quality): self
  {
    if ($this->isOversize) {
      return $this;
    }
    $this->imagick->setImageCompressionQuality($quality);

    // the image quality is changed, we should unset saved flag
    $this->isSaved = false;
    return $this;
  }

  /**
   * Summary of fixImageOrientation
   * @return ImageProcessor
   */
  public function fixImageOrientation()
  {
    $format = strtoupper($this->imagick->getImageFormat());

    // Skip formats that don't usually include orientation data
    if ($format !== 'JPEG' && $format !== 'TIFF') {
      return $this;
    }

    $orientation = $this->imagick->getImageOrientation();

    // Skip images that don't have orientation data
    if ($orientation === Imagick::ORIENTATION_UNDEFINED || $orientation === Imagick::ORIENTATION_TOPLEFT) {
      return $this;
    }

    switch ($orientation) {
      case Imagick::ORIENTATION_TOPRIGHT:
        $this->imagick->flopImage();
        break;
      case Imagick::ORIENTATION_BOTTOMRIGHT:
        $this->imagick->rotateimage("#FFF", 180);
        break;
      case Imagick::ORIENTATION_BOTTOMLEFT:
        $this->imagick->flopImage();
        $this->imagick->rotateimage("#FFF", 180);
        break;
      case Imagick::ORIENTATION_LEFTTOP:
        $this->imagick->flopImage();
        $this->imagick->rotateimage("#FFF", -90);
        break;
      case Imagick::ORIENTATION_RIGHTTOP:
        $this->imagick->rotateimage("#FFF", 90);
        break;
      case Imagick::ORIENTATION_RIGHTBOTTOM:
        $this->imagick->flopImage();
        $this->imagick->rotateimage("#FFF", 90);
        break;
      case Imagick::ORIENTATION_LEFTBOTTOM:
        $this->imagick->rotateimage("#FFF", -90);
        break;
    }

    $this->imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

    // the image orientation has changed, we should unset saved flag
    $this->isSaved = false;

    return $this;
  }

  /**
   * Summary of resizeImage
   * @param mixed $width
   * @param mixed $height
   * @return ImageProcessor
   */
  public function resizeImage($width, $height): self
  {
    // Fetch image format
    $imageFormat = strtolower($this->imagick->getImageFormat());
    // Fetch dimensions of the first frame
    $currentWidth = $this->imagick->getImageWidth();
    $currentHeight = $this->imagick->getImageHeight();

    error_log("Image format: $imageFormat" . PHP_EOL);
    error_log("Current width: $currentWidth" . PHP_EOL);
    error_log("Current height: $currentHeight" . PHP_EOL);

    // Check if the image format is GIF and do nothing
    if ($imageFormat === 'gif') {
      return $this;
    }

    // Resize is required
    if ($currentWidth > $width || $currentHeight > $height) {
      $imagick = $this->imagick->coalesceImages();

      foreach ($imagick as $frame) {
        // Don't resize if the image is smaller than the new dimensions
        if ($frame->getImageWidth() > $width || $frame->getImageHeight() > $height) {
          $frame->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
        }
      }

      $this->imagick = $imagick->deconstructImages();
      $this->isSaved = false;  // The image dimensions are changed
    }

    return $this;
  }

  /**
   * Summary of cropSquare
   * @return ImageProcessor
   */
  public function cropSquare(): self
  {
    // Fetch dimensions of the first frame
    $width = $this->imagick->getImageWidth();
    $height = $this->imagick->getImageHeight();

    // If the image is not a square
    if ($width !== $height) {
      $imagick = $this->imagick->coalesceImages();

      foreach ($imagick as $frame) {
        $width = $frame->getImageWidth();
        $height = $frame->getImageHeight();

        // find smallest dimension
        $min = min($width, $height);

        // calculate coordinates for a centered square crop
        $x = ($width - $min) / 2;
        $y = ($height - $min) / 2;

        // crop the image
        $frame->cropImage($min, $min, $x, $y);
        // Reset the image page to the new dimensions
        $frame->setImagePage($min, $min, 0, 0);
        // Resize the cropped image to square dimensions
        $frame->resizeImage($min, $min, Imagick::FILTER_LANCZOS, 1);
      }

      $this->imagick = $imagick->deconstructImages();

      // The image dimensions are changed, we should unset saved flag
      $this->isSaved = false;
    }

    return $this;
  }

  /**
   * Summary of convertHeicToJpeg
   * @return ImageProcessor
   */
  public function convertHeicToJpeg(): self
  {
    // Using mimetypes detect if image is HEIC or HEIF
    $mimeType = mime_content_type($this->imagePath);
    if ($mimeType === 'image/heic' || $mimeType === 'image/heif') {
      try {
        $command = $this->heifConverter . " -q 100 " . escapeshellarg($this->imagePath) . " " . escapeshellarg($this->imagePath . ".jpg");
        // Execute the command
        exec($command, $output, $returnCode);
        if ($returnCode === 0) {
          // Delete the original HEIC/HEIF image
          unlink($this->imagePath);
          // Rename the converted image to the original image name
          rename($this->imagePath . ".jpg", $this->imagePath);
          // Cleanup other files produced by heif-convert
          $pathInfo = pathinfo($this->imagePath);
          $glob = glob($pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-*');
          foreach ($glob as $file) {
            if ($file !== $this->imagePath) {
              unlink($file);
            }
          }
          // Reinitialize Imagick after converting
          $this->reinitializeImagick(force: true);
          $this->isSaved = false;
        } else {
          error_log("Could not convert HEIC/HEIF image: $this->imagePath" . PHP_EOL);
        }
      } catch (Exception $e) {
        error_log($e->getMessage() . PHP_EOL);
      }
    }
    return $this;
  }
  /**
   * Summary of convertToJpeg
   * @return ImageProcessor
   */
  public function convertToJpeg(): self
  {
    $imageFormat = strtolower($this->imagick->getImageFormat());

    error_log("Image format: $imageFormat" . PHP_EOL);
    // Check if the image format is already JPEG
    if ($imageFormat === 'jpeg') {
      error_log("Image is already JPEG, not converting." . PHP_EOL);
      return $this;
    }

    // For the animation check, we'll use getNumberImages() which returns 1 for non-animated images.
    // For animated GIFs, it will return the number of animation frames (more than 1)
    $isAnimated = $this->imagick->getNumberImages() > 1;

    // Check if the image format is one of the formats that support animation and if it's animated
    if (($imageFormat === 'gif' || $imageFormat === 'png' || $imageFormat === 'mng' || $imageFormat === 'tiff') && $isAnimated) {
      return $this;
    }

    // If the image is PNG, check for transparency
    if ($imageFormat === 'png') {
      $isTransparent = $this->imagick->getImageAlphaChannel();

      // If image is transparent, return without converting
      if ($isTransparent) {
        return $this;
      }
    }

    // If we got to this point, it's safe to convert the image to JPEG
    $this->imagick->setImageFormat('jpeg');

    // The image format has changed, we should unset saved flag
    $this->isSaved = false;

    return $this;
  }

  /**
   * Summary of convertTiffToJpeg
   * @return ImageProcessor
   */
  public function convertTiffToJpeg(): self
  {
    $imageFormat = strtolower($this->imagick->getImageFormat());

    error_log("Image format: $imageFormat" . PHP_EOL);
    // Check if the image format is TIFF
    if ($imageFormat !== 'tiff') {
      error_log("Image is not TIFF, not converting." . PHP_EOL);
      return $this;
    }

    // For the animation check, we'll use getNumberImages() which returns 1 for non-animated images.
    // For animated GIFs, it will return the number of animation frames (more than 1)
    $isAnimated = $this->imagick->getNumberImages() > 1;

    // Check if the image format is one of the formats that support animation and if it's animated
    if ($isAnimated) {
      return $this;
    }

    // If we got to this point, it's safe to convert the image to JPEG
    $this->imagick->setImageFormat('jpeg');

    // The image format has changed, we should unset saved flag
    $this->isSaved = false;

    return $this;
  }

  /**
   * Summary of stripImageMetadata
   * @return ImageProcessor
   */
  public function stripImageMetadata(): self
  {
    // Do nothing for formats that don't support metadata
    $imageFormat = strtolower($this->imagick->getImageFormat());
    if ($imageFormat !== 'jpeg' || $imageFormat !== 'heic' || $imageFormat !== 'heif' || $imageFormat !== 'webp' || $imageFormat !== 'tiff') {
      return $this;
    }
    // Save the ICC profile if one exists
    $iccProfile = null;
    try {
      $iccProfile = $this->imagick->getImageProfile('icc');
    } catch (Exception $e) {
      // No ICC profile, do nothing
      error_log($e->getMessage() . PHP_EOL);
    }

    // Save the Exif ColorSpace property if one exists
    $colorSpace = null;
    try {
      $exif = $this->imagick->getImageProperties("exif:*");
      if (isset($exif["exif:ColorSpace"])) {
        $colorSpace = $exif["exif:ColorSpace"];
      }
    } catch (Exception $e) {
      // No Exif ColorSpace property, do nothing
      error_log($e->getMessage() . PHP_EOL);
    }

    // Strip all profiles and comments
    try {
      $this->imagick->stripImage();
    } catch (Exception $e) {
      error_log($e->getMessage() . PHP_EOL);
    }

    // Reassign ICC profile if it existed
    if ($iccProfile !== null) {
      $this->imagick->profileImage('icc', $iccProfile);
    }

    // Reassign Exif ColorSpace property if it existed
    if ($colorSpace !== null) {
      $this->imagick->setImageProperty('exif:ColorSpace', $colorSpace);
    }

    // The image metadata is changed, we should unset saved flag
    $this->isSaved = false;
    return $this;
  }

  /**
   * Summary of getImageMetadata
   * @return array
   */
  public function getImageMetadata(): array
  {
    return $this->imagick->getImageProperties("*", true);
  }

  /**
   * Summary of getImageDimensions
   * @return array
   */
  public function getImageDimensions(): array
  {
    return [
      'width' => $this->imagick->getImageWidth(),
      'height' => $this->imagick->getImageHeight()
    ];
  }

  /**
   * Summary of optimiseImage
   * @throws \RuntimeException
   * @return ImageProcessor
   */
  public function optimiseImage(): self
  {
    // Use palettegen and paletteuse to preserve colors and reduce artifacts
    $imageFormat = strtolower($this->imagick->getImageFormat());
    if ($imageFormat === 'gif') {
      return $this;
    }
    // Get image dimensions and return if over 4096x4096 pixels for PNG
    if ($imageFormat === 'png' && $this->isOversize) {
      return $this;
    }

    if (!$this->isSaved) {
      throw new RuntimeException("Please call save() method before optimizeImage");
    }
    $jpegQuality = '--max=85';
    $pngQuality = '--quality=100';
    $webpQuality = '-q 80';

    $optimizerChain = (new OptimizerChain())
      ->addOptimizer(new Jpegoptim([
        $jpegQuality,
        '--strip-none', // We strip metadata using ImageMagick, so we can save ICC profile
        '--all-progressive',
      ]))

      ->addOptimizer(new Optipng([
        '-i0',
        '-o2',
        '-nx',
        '-fix',
        '-quiet',
      ]))

      ->addOptimizer(new Pngquant([
        $pngQuality,
        '--force',
        '--skip-if-larger',
      ]))

      ->addOptimizer(new Svgo([
        '--config=svgo.config.js',
      ]))

      ->addOptimizer(new Gifsicle([
        '-b',
        '-O3',
        '--careful',
      ]))

      ->addOptimizer(new Cwebp([
        $webpQuality,
        '-m 6',
        '-pass 10',
        '-mt',
      ]));
    $optimizerChain->setTimeout(60) // Set 60 seconds timeout
      ->optimize($this->imagePath);

    // Reinitialize Imagick after optimizing
    $this->reinitializeImagick();

    return $this;
  }

  /**
   * Calculate blurhash for the image
   * 
   * @param int $xComponent Number of components in X direction
   * @param int $yComponent Number of components in Y direction
   * @param int $resizeWidth Resize image to this width before calculating blurhash
   * @param int $resizeHeight Resize image to this height before calculating blurhash
   * 
   * Example usage:
   * $imageProcessor = new ImageProcessor($imagePath);
   * $blurhash = $imageProcessor->calculateBlurhash();
   * echo "Blurhash: $blurhash";
   * 
   * @return string
   */
  public function calculateBlurhash(int $xComponent = 4, int $yComponent = 3, $resizeWidth = 40, $resizeHeight = 30): string
  {
    // Return generic blurhash if image is oversize
    if ($this->isOversize) {
      return "LEHV6nWB2yk8pyo0adR*.7kCMdnj";
    }
    // Re-init Imagick to avoid errors
    $this->reinitializeImagick();
    // Clone the current imagick instance to not affect the original image
    $imagick = clone $this->imagick;

    // Resize the image to a smaller size for faster processing
    $imagick->resizeImage($resizeWidth, $resizeHeight, Imagick::FILTER_TRIANGLE, 1);

    $pixels = [];
    $iterator = $imagick->getPixelIterator();

    foreach ($iterator as $row => $pixelsRow) {
      $pixelRow = [];
      foreach ($pixelsRow as $column => $pixel) {
        $color = $pixel->getColor();
        $pixelRow[] = [$color['r'], $color['g'], $color['b']];
      }
      $pixels[] = $pixelRow;
      $iterator->syncIterator();
    }

    // Clear and destroy the resized image
    $imagick->clear();
    $imagick->destroy();

    $blurhash = Blurhash::encode($pixels, $xComponent, $yComponent);
    return $blurhash;
  }

  /**
   * Summary of save
   * @throws \RuntimeException
   * @return void
   */
  public function save(): void
  {
    if ($this->imagick->getNumberImages() > 1) {
      // This is an animated image (like a GIF)
      if (!$this->imagick->writeImages($this->imagePath, true)) {
        throw new RuntimeException("Could not write image: {$this->imagePath}");
      }
    } else {
      // This is not an animated image
      if (!$this->imagick->writeImage($this->imagePath)) {
        throw new RuntimeException("Could not write image: {$this->imagePath}");
      }
    }
    $this->isSaved = true;
  }

  /**
   * Summary of reinitializeImagick
   * @return void
   */
  private function reinitializeImagick(bool $force = false): void
  {
    if ($this->isSaved || $force) {
      $this->imagick->clear();
      $this->imagick->destroy();
      $this->imagick = new Imagick(realpath($this->imagePath));
    }
  }

  /**
   * Summary of __destruct
   */
  public function __destruct()
  {
    $this->imagick->clear();
    $this->imagick->destroy();
  }
}
