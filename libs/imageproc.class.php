<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Spatie\ImageOptimizer\OptimizerChainFactory;
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
class ImageProcessor
{
  private $imagick;
  private $isSaved = false;
  private $imagePath;
  private static $supportedFormats;

  public function __construct($imagePath)
  {
    if (!file_exists($imagePath)) {
      throw new InvalidArgumentException("File does not exist: $imagePath");
    }

    $this->imagePath = $imagePath;
    $this->imagick = new Imagick(realpath($imagePath));

    if (!self::isSupported($this->imagick->getImageFormat())) {
      throw new InvalidArgumentException("Image format not supported: {$this->imagick->getImageFormat()}");
    }
  }

  public static function isSupported($format): bool
  {
    if (self::$supportedFormats === null) {
      self::$supportedFormats = Imagick::queryFormats();
    }
    return in_array(strtoupper($format), self::$supportedFormats);
  }

  public function reduceQuality($quality): self
  {
    $this->imagick->setImageCompressionQuality($quality);

    // the image quality is changed, we should unset saved flag
    $this->isSaved = false;
    return $this;
  }

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

  public function resizeImage($width, $height): self
  {
    // Fetch dimensions of the first frame
    $currentWidth = $this->imagick->getImageWidth();
    $currentHeight = $this->imagick->getImageHeight();

    // Resize is required
    if ($currentWidth > $width || $currentHeight > $height) {
      $imagick = $this->imagick->coalesceImages();

      foreach ($imagick as $frame) {
        $frame->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
      }

      $this->imagick = $imagick->deconstructImages();
      $this->isSaved = false;  // The image dimensions are changed
    }

    return $this;
  }

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

  public function convertToJpeg(): self
  {
    $imageFormat = strtolower($this->imagick->getImageFormat());

    // Check if the image format is already JPEG
    if ($imageFormat === 'jpeg') {
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

  public function stripImageMetadata(): self
  {
    $this->imagick->stripImage();

    // the image metadata is changed, we should unset saved flag
    $this->isSaved = false;
    return $this;
  }

  public function getImageMetadata(): array
  {
    return $this->imagick->getImageProperties("*", true);
  }

  public function getImageDimensions(): array
  {
    return [
      'width' => $this->imagick->getImageWidth(),
      'height' => $this->imagick->getImageHeight()
    ];
  }

  public function optimiseImage(): self
  {
    if (!$this->isSaved) {
      throw new RuntimeException("Please call save() method before optimizeImage");
    }
    $optimizerChain = OptimizerChainFactory::create();
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
    // Re-init Imagick to avoid errors
    $this->reinitializeImagick();
    // Clone the current imagick instance to not affect the original image
    $imagick = clone $this->imagick;

    // Resize the image to a smaller size for faster processing
    $imagick->resizeImage($resizeWidth, $resizeHeight, Imagick::FILTER_TRIANGLE, 1);

    $width = $imagick->getImageWidth();
    $height = $imagick->getImageHeight();

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

  private function reinitializeImagick(): void
  {
    if ($this->isSaved) {
      $this->imagick->clear();
      $this->imagick->destroy();
      $this->imagick = new Imagick(realpath($this->imagePath));
    }
  }

  public function __destruct()
  {
    $this->imagick->clear();
    $this->imagick->destroy();
  }
}
