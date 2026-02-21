<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";

/**
 * Converts media to GIF using ffmpeg/gifsicle.
 * It scales and crops output to a square GIF.
 * Example usage:
 * try {
 *     $gifConverter = new GifConverter();
 *
 *     // Optionally set paths and crop parameters if you don't want to use defaults
 *     // $gifConverter->setFfmpegPath('/path/to/ffmpeg')
 *     //              ->setCropParameters(150, 150, '(in_w-150)/2', '(in_h-150)/2');
 *
 *     $inputFile = '/path/to/input/file';
 *     $outputFile = $gifConverter->convertToGif($inputFile);
 *
 *     echo "Output file: {$outputFile}\n";
 *
 * } catch (Exception $e) {
 *     echo 'Error: ' . $e->getMessage();
 * }
 */
class GifConverter
{
  /**
    * Path to ffmpeg binary.
   * @var string
   */
  private $ffmpegPath = "/usr/local/bin/ffmpeg";
  /**
    * Path to ffprobe binary.
   * @var string
   */
  private $ffprobePath = "/usr/local/bin/ffprobe";
  private $gifsiclePath = "/usr/bin/gifsicle";
  /**
    * Output crop width.
   * @var int
   */
  private $cropWidth = 150;
  /**
    * Output crop height.
   * @var int
   */
  private $cropHeight = 150;
  /**
    * Output GIF frame rate.
   * @var int
   */
  private $gifFPS = 12;
  /**
    * Output GIF duration in seconds.
   * @var int
   */
  private $gifDuration = 10;
  /**
    * Temporary output file path.
   * @var 
   */
  private $tempFile;

  /**
    * Maximum GIF width.
   * @var int
   */
  private $maxGifWidth = 360;
  private $maxGifHeight = 360;


  /**
    * Initialize converter.
   */
  public function __construct()
  {
    $this->tempFile = generateUniqueFilename('gifconv_', sys_get_temp_dir()) . '.gif';
  }

  /**
    * Set ffmpeg binary path.
   * @param mixed $path
   * @return GifConverter
   */
  public function setFfmpegPath($path): self
  {
    $this->ffmpegPath = $path;
    return $this;
  }

  /**
    * Set ffprobe binary path.
    * @param string $ffprobePath
   * @return self
   */
  public function setFfprobePath($ffprobePath): self
  {
    $this->ffprobePath = $ffprobePath;
    return $this;
  }

  /**
    * Set crop and GIF generation parameters.
   * @param mixed $width
   * @param mixed $height
   * @param mixed $gifDuration
   * @param mixed $gifFPS
   * @return GifConverter
   */
  public function setCropParameters($width = null, $height = null, $gifDuration = null, $gifFPS = null): self
  {
    $this->cropWidth = $width ?? $this->cropWidth;
    $this->cropHeight = $height ?? $this->cropHeight;
    $this->gifDuration = $gifDuration ?? $this->gifDuration;
    $this->gifFPS = $gifFPS ?? $this->gifFPS;
    return $this;
  }

  /**
    * Convert input media to GIF.
   * @param mixed $inputFile
   * @throws \Exception
   * @return string
   */
  public function convertToGif($inputFile): string
  {
    if (!$inputFile) {
      throw new Exception('No input file specified. Please provide a file path.');
    }

    // All the magic happens in the following line
    $gifCommand = "{$this->ffmpegPath} -y -hide_banner -t {$this->gifDuration} -i {$inputFile} -filter_complex \"[0:v] fps={$this->gifFPS},scale=w='if(gt(iw,ih),-1,{$this->cropWidth})':h='if(gt(iw,ih),{$this->cropHeight},-1)',crop={$this->cropWidth}:{$this->cropHeight},split [a][b];[a] palettegen=stats_mode=full [p];[b][p] paletteuse=dither=sierra2_4a\" {$this->tempFile} 2>&1";

    exec($gifCommand, $output, $returnVar);

    if ($returnVar !== 0) {
      throw new Exception("Error running FFmpeg command to generate gif: " . implode("\n", $output));
    }

    // Optimize the gif
    $this->downsizeGif($this->tempFile);

    return $this->tempFile;
  }

  /**
    * Optimize and downsize GIF output.
   * @param mixed $inputFile
   * @throws \Exception
   * @return string
   */
  public function downsizeGif($inputFile): string
  {
    if (!$inputFile) {
      throw new Exception('No input file specified. Please provide a file path.');
    }

    // Now optimize the gif
    $optimizeCommand = "{$this->gifsiclePath} --careful --resize-fit {$this->maxGifWidth}x{$this->maxGifHeight} -O3 -b --lossy=15 {$inputFile} -o {$this->tempFile} 2>&1";

    exec($optimizeCommand, $output, $returnVar);

    if ($returnVar !== 0) {
      throw new Exception("Error running gifsicle command to optimize gif: " . implode("\n", $output));
    }

    return $this->tempFile;
  }

  /**
    * Set maximum GIF width.
    * @param int $maxGifWidth
   * @return self
   */
  public function setMaxGifWidth($maxGifWidth): self
  {
    $this->maxGifWidth = $maxGifWidth;
    return $this;
  }

  /**
    * Set maximum GIF height.
    * @param int $maxGifHeight
   * @return self
   */
  public function setMaxGifHeight($maxGifHeight): self
  {
    $this->maxGifHeight = $maxGifHeight;
    return $this;
  }

  /**
    * Destructor.
   */
  function __destruct()
  {
    if (file_exists($this->tempFile)) {
      unlink($this->tempFile);
    }
  }
}
