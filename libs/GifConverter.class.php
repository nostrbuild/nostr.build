<?php

/**
 * Summary of GifConverter
 * Converts a video file to a gif using ffmpeg, it will scale and crop the video to a square gif.
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
   * Summary of ffmpegPath
   * @var string
   */
  private $ffmpegPath = "/usr/local/bin/ffmpeg";
  /**
   * Summary of cropWidth
   * @var int
   */
  private $cropWidth = 150;
  /**
   * Summary of cropHeight
   * @var int
   */
  private $cropHeight = 150;
  /**
   * Summary of gifFPS
   * @var int
   */
  private $gifFPS = 12;
  /**
   * Summary of gifDuration
   * @var int
   */
  private $gifDuration = 10;
  /**
   * Summary of tempFile
   * @var 
   */
  private $tempFile;

  /**
   * Summary of __construct
   */
  public function __construct()
  {
    $this->tempFile = sys_get_temp_dir() . '/gifconv_' . uniqid() . '.gif';
  }

  /**
   * Summary of setFfmpegPath
   * @param mixed $path
   * @return GifConverter
   */
  public function setFfmpegPath($path)
  {
    $this->ffmpegPath = $path;
    return $this;
  }

  /**
   * Summary of setCropParameters
   * @param mixed $width
   * @param mixed $height
   * @param mixed $gifDuration
   * @param mixed $gifFPS
   * @return GifConverter
   */
  public function setCropParameters($width = null, $height = null, $gifDuration = null, $gifFPS = null)
  {
    $this->cropWidth = $width ?? $this->cropWidth;
    $this->cropHeight = $height ?? $this->cropHeight;
    $this->gifDuration = $gifDuration ?? $this->gifDuration;
    $this->gifFPS = $gifFPS ?? $this->gifFPS;
    return $this;
  }

  /**
   * Summary of convertToGif
   * @param mixed $inputFile
   * @throws \Exception
   * @return string
   */
  public function convertToGif($inputFile)
  {
    if (!$inputFile) {
      throw new Exception('No input file specified. Please provide a file path.');
    }

    // All the magic happens in the following line
    $gifCommand = "{$this->ffmpegPath} -y -hide_banner -t {$this->gifDuration} -i {$inputFile} -filter_complex \"[0:v] fps={$this->gifFPS},scale=w='if(gt(iw,ih),-1,{$this->cropWidth})':h='if(gt(iw,ih),{$this->cropHeight},-1)',crop={$this->cropWidth}:{$this->cropHeight},split [a][b];[a] palettegen [p];[b][p] paletteuse\" {$this->tempFile} 2>&1";

    exec($gifCommand, $output, $returnVar);

    if ($returnVar !== 0) {
      throw new Exception("Error running FFmpeg command to generate gif: " . implode("\n", $output));
    }

    return $this->tempFile;
  }

  /**
   * Summary of __destruct
   */
  function __destruct()
  {
    if (file_exists($this->tempFile)) {
      unlink($this->tempFile);
    }
  }
}
