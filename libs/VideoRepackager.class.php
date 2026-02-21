<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/VideoTranscoding.class.php";

class VideoRepackager
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
  /**
    * Temporary output file path.
   * @var 
   */
  private $tempFile;
  /**
    * Video metadata helper instance.
   * @var 
   */
  private $videoInfoClass;
  /**
    * Source video file path.
   * @var 
   */
  private $videoFile;
  /**
    * Repackaging timeout in seconds.
   * @var 
   */
  private $timeout;

  /**
    * Maximum GIF width (legacy config, currently unused).
   * @var int
   */
  private $maxGifWidth = 500;
  private $maxGifHeight = 500;


  /**
    * Initialize repackager.
   */
  public function __construct(string $videoFile)
  {
    $this->videoInfoClass = new VideoInformation($videoFile);
    $this->videoFile = $videoFile;

    // Set reasonable timeout for video repackaging
    $this->timeout = 45;

    $this->tempFile = generateUniqueFilename('vidrepack_', sys_get_temp_dir()) . '.mp4';
  }

  /**
    * Set ffmpeg binary path.
   * @param mixed $path
    * @return self
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
    * Convert input to GIF (legacy method retained as comment block).
   * @param mixed $inputFile
   * @throws \Exception
   * @return string
   */
  /*
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

    return $this->tempFile;
  }
  */

  public function repackageVideo(): string
  {

    $inputFile = $this->videoFile;
    if (!$inputFile || !file_exists($inputFile)) {
      return $inputFile;
    }

    // Check if video has audio and if audio is AAC
    $audioCodec = $this->videoInfoClass->get_audio_codec();
    $audioCodecParam = "-c:a copy"; // Copy audio by default
    if ($audioCodec !== 'aac' && !empty($audioCodec)) {
      $audioCodecParam = "-c:a aac"; // Re-encode audio to AAC
    } elseif (empty($audioCodec)) {
      $audioCodecParam = ""; // No audio
    }


    $compatibleVideoCodecs = ['h264', 'hevc', 'mpeg4', 'mpeg2video', 'av01'];
    $videoCodec = $this->videoInfoClass->get_video_codec();
    if (!in_array($videoCodec, $compatibleVideoCodecs)) {
      return $inputFile;
    }


    // Add tag to h265/hevc video to make it compatible with iOS
    $tagParam = "";
    if ($videoCodec === 'hevc') {
      $tagParam = "-tag:v hvc1";
    }


    // Escape file paths for shell safety
    $inputFileEsc = escapeshellarg($inputFile);
    $tempFileEsc = escapeshellarg($this->tempFile);
    $repackCommand = "timeout {$this->timeout} nice -n 19 {$this->ffmpegPath} -y -hide_banner -i {$inputFileEsc} -c:v copy {$audioCodecParam} {$tagParam} -map 0:v -map 0:a? -movflags +faststart -f mp4 {$tempFileEsc} 2>&1";
    try {
      exec($repackCommand, $output, $returnVar);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return $inputFile;
    }

    if ($returnVar !== 0 || !file_exists($this->tempFile) || filesize($this->tempFile) === 0) {
      error_log("Error running FFmpeg command to repackage video: " . implode("\n", $output));
      // Clean up possibly corrupt temp file
      if (file_exists($this->tempFile)) {
        unlink($this->tempFile);
      }
      return $inputFile;
    }

    return $this->tempFile;
  }




  /**
    * Destructor.
   */
  function __destruct() {}
}
