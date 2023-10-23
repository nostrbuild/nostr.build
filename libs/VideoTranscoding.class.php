<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Sqs\SqsClient;

/* DynamoDB Table Specs

Table Name: VideoCatalog
Partition Key: videoId (String)
Sort Key: recordType (String)

General Info:
videoId: Unique identifier for the video. (String)
recordType: Record type, e.g., 'video#metadata'. (String)
createdAt: Timestamp when the video was submitted. (String in 'Y-m-d H:i:s' format)
updatedAt: Timestamp when the video was last updated. (String in 'Y-m-d H:i:s' format)
userNpub: User's npub or a default value. (String)
checksum: SHA256 hash of the original video file. (String)
filename: Original filename of the video. (String)
size: Size of the video file in bytes. (Number)
duration: Total duration of the video in seconds. (Number)

Descriptive Info:
title: Title of the video. (String)
description: Description or synopsis of the video. (String)
album: Album or collection the video belongs to. (String)
tags: Tags associated with the video. (List of Strings)
license: Licensing information of the video. (String)
copyright: Copyright information for the video. (String)
nsfw: Flag indicating if the content is not safe for work. (Boolean)
category: Category to which the video belongs. (String)
language: Language of the video content. (String)
location: Geographical location related to the video content. (String)

Video Permissions:
allowComments: Permission to comment on the video. (Boolean)
allowEmbedding: Permission to embed the video. (Boolean)
allowRating: Permission to rate the video. (Boolean)
allowSharing: Permission to share the video. (Boolean)
allowDownload: Permission to download the video. (Boolean)
privacy: Video's privacy setting, e.g., 'public', 'private'. (String)
Generated Video Files Info:
subtitlesUrl: URL to subtitles file. (String)
posterUrl: URL to a poster image. (String)
animatedPosterUrl: URL to an animated poster/gif. (String)
storyboardUrl: URL to the video storyboard. (String)
storyboardVttUrl: URL to a VTT file associated with the storyboard. (String)
defaultVideoUrl: URL to the video file. (String)
videoUrlHls: URL to the HLS stream of the video. (String)
videoUrlDash: URL to the DASH stream of the video. (String)
variantVideos: List of generated video files with metadata. (List of Maps, with each map containing attributes like 'url', 'resolution', 'codec', etc.)

  Each variant video map contains the following attributes:
  url (String): Direct URL to the video.
  resolution (String): Resolution of the video (e.g., '1080p').
  codec (String): Video codec used.
  profile (String): Profile level of the codec.
  level (String): Level specification of the codec.
  bitrate (Number): Video bitrate.
  framerate (String): Frame rate of the video.
  duration (Number): Duration of the video in seconds.
  size (Number): Size of the video file in bytes.
  checksum (String): Checksum for the video file.
  isHdr (Boolean): Indicates if the video is in HDR.
  pixelFormat (String): Pixel format used in the video.
  isVerticalVideo (Boolean): Indicates if the video is vertical.
  orientation (Number): Orientation of the video.
  mimeType (String): MIME type for the video file.
  audioCodec (String): Audio codec used.
  audioSampleRate (Number): Audio sample rate.
  audioChannels (Number): Number of audio channels.
  audioChannelLayout (String): Audio channel layout.
  audioBitRate (Number): Audio bitrate.

Video Stream:
chapters: Chapters or segments within the video. (List of Maps)
width: Width of the video in pixels. (Number)
height: Height of the video in pixels. (Number)
avgFramerate: Average framerate of the video. (String)
framerate: Framerate of the video. (String)
framesCount: Total number of frames in the video. (Number)
pixelFormat: Pixel format used in the video. (String)
colorPrimaries: Color primaries used in the video. (String)
colorTransfer: Color transfer used in the video. (String)
colorSpace: Color space used in the video. (String)
colorRange: Color range used in the video. (String)
format_name: Format or container of the video. (String)
bit_rate: Bitrate of the video. (Number)
encoder: Software or library used to encode the video. (String)
videoCodec: Video codec used. (String)
videoProfile: Profile of the video codec. (String)
videoLevel: Level of the video codec. (String)
displayAspectRatio: Display aspect ratio of the video. (String)
videoBitRate: Bitrate of the video stream. (Number)
videoDuration: Duration of the video stream in seconds. (Number)
isHdr: Flag indicating if the video is in HDR. (Boolean)
isVerticalVideo: Flag indicating if the video is vertical. (Boolean)
orientation: Orientation of the video. (Number)
originalMimeType: MIME type of the video. (String)

Audio Stream:
audioCodec: Audio codec used. (String)
audioSampleRate: Sample rate of the audio in Hz. (Number)
audioChannels: Number of audio channels. (Number)
audioChannelLayout: Channel layout of the audio. (String)
audioBitRate: Bitrate of the audio stream. (Number)
audioDuration: Duration of the audio stream in seconds. (Number)

SQS (Simple Queue Service):
mediaStatus: Current processing status of the video. Values: Pending, Submitted, Processing, Completed, Failed (String) 
sourceVideoUrl: URL to the original video file. (String)
desiredCodecs: Desired codecs for transcoding. (List of Strings)
desiredResolutions: Desired resolutions for transcoding. (List of Strings)
jobId: Job ID of the video transcoding job. (String)
*/

class VideoInformation
{

  private $ffprobePath = "/usr/local/bin/ffprobe";

  private $video_file;

  private $video_ffprobe_json = null;

  public function __construct(string $video_file)
  {
    $this->video_file = $video_file;
  }

  public function get_video_info(): array
  {
    // Return cached value if available
    if ($this->video_ffprobe_json !== null) {
      return $this->video_ffprobe_json;
    }

    $cmd = $this->ffprobePath .
      " -v quiet -print_format json -show_format -show_streams -show_programs -show_chapters -show_private_data " .
      escapeshellarg($this->video_file);
    $this->video_ffprobe_json = json_decode(shell_exec($cmd), true);
    return $this->video_ffprobe_json;
  }

  public function get_video_duration(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'video') {
        return intval($stream['duration']);
      }
    }
    return 0;
  }

  public function get_video_width(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'video') {
        return intval($stream['width']);
      }
    }
    return 0;
  }

  public function get_video_height(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'video') {
        return intval($stream['height']);
      }
    }
    return 0;
  }

  public function get_video_bitrate(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'video') {
        return intval($stream['bit_rate']);
      }
    }
    return 0;
  }

  public function get_video_codec(): string
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'video') {
        return $stream['codec_name'];
      }
    }
    return "";
  }

  public function get_audio_codec(): string
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'audio') {
        return $stream['codec_name'];
      }
    }
    return "";
  }

  public function get_audio_bitrate(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'audio') {
        return intval($stream['bit_rate']);
      }
    }
    return 0;
  }

  public function get_audio_sample_rate(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'audio') {
        return intval($stream['sample_rate']);
      }
    }
    return 0;
  }

  public function get_audio_channels(): int
  {
    $info = $this->get_video_info();
    foreach ($info['streams'] as $stream) {
      if ($stream['codec_type'] === 'audio') {
        return intval($stream['channels']);
      }
    }
    return 0;
  }

  public function get_video_framerate(): array
  {
    $video_info = $this->get_video_info();
    $video_frame_rate = [];
    foreach ($video_info["streams"] as $stream) {
      if ($stream["codec_type"] == "video") {
        $video_frame_rate["frame_rate"] = $stream["r_frame_rate"];
        $video_frame_rate["avg_frame_rate"] = $stream["avg_frame_rate"];
        break;
      }
    }
    return $video_frame_rate;
  }

  public function get_video_color_parameters(): array
  {
    // Get video info
    $video_info = $this->get_video_info();

    // Get video color parameters
    $video_color_parameters = array();
    foreach ($video_info["streams"] as $stream) {
      if ($stream["codec_type"] == "video") {
        $video_color_parameters["color_primaries"] = isset($stream["color_primaries"]) ? $stream["color_primaries"] : "bt709";
        $video_color_parameters["color_transfer"] = isset($stream["color_transfer"]) ? $stream["color_transfer"] : "bt709";
        $video_color_parameters["color_space"] = isset($stream["color_space"]) ? $stream["color_space"] : "bt709";
        $video_color_parameters["pix_fmt"] = isset($stream["pix_fmt"]) ? $stream["pix_fmt"] : "yuv420p";
        $video_color_parameters["color_range"] = isset($stream["color_range"]) ? $stream["color_range"] : "tv";
        $video_color_parameters["dv_profile"] = isset($stream["dv_profile"]) ? $stream["dv_profile"] : null;
        break;
      }
    }
    return $video_color_parameters;
  }

  public function is_hdr(): bool
  {
    // Get video color parameters
    $video_color_parameters = $this->get_video_color_parameters();
    if ($video_color_parameters === null) {
      return false;
    }

    if (
      $video_color_parameters["color_space"] === "bt2020nc" &&
      $video_color_parameters["color_primaries"] === "bt2020" &&
      in_array($video_color_parameters["color_transfer"], array("smpte2084", "arib-std-b67", "smpte428", "iec61966_2_1"))
    ) {
      return true;
    } else {
      return false;
    }
  }

  public function get_video_resolution(): array
  {
    // Get video info
    $video_info = $this->get_video_info();

    // Get video resolution
    $video_resolution = array();
    foreach ($video_info["streams"] as $stream) {
      if ($stream["codec_type"] == "video") {
        $video_resolution["width"] = $stream["width"];
        $video_resolution["height"] = $stream["height"];
        break;
      }
    }
    return $video_resolution;
  }

  public function get_video_orientation(): array
  {
    $video_info = $this->get_video_info();
    $video_orientation = array("rotate" => "0");

    foreach ($video_info["streams"] as $stream) {
      if ($stream["codec_type"] === "video") {
        // Check for Display Matrix
        if (array_key_exists("side_data_list", $stream)) {
          foreach ($stream["side_data_list"] as $side_data) {
            if (array_key_exists("side_data_type", $side_data) && $side_data["side_data_type"] === "Display Matrix") {
              $video_orientation["rotate"] = array_key_exists("rotation", $side_data) ? $side_data["rotation"] : "0";
              break;  // Exit the side_data loop
            }
          }
        }

        // Check for rotate tags
        $rotate_tag = isset($stream["tags"]["rotate"]) ? $stream["tags"]["rotate"] : null;
        if ($rotate_tag !== null && in_array($rotate_tag, array("90", "180", "270"))) {
          $video_orientation["rotate"] = $rotate_tag;
          break;  // Exit the stream loop
        }
      }
    }

    // If no explicit rotation found, default based on width and height
    if ($video_orientation["rotate"] === "0") {
      $video_dimension = $this->get_video_resolution();
      if ($video_dimension && $video_dimension["width"] > $video_dimension["height"]) {
        $video_orientation["rotate"] = "0";
      } else {
        $video_orientation["rotate"] = "90";
      }
    }
    return $video_orientation;
  }

  public function get_video_aspect_ratio(): array
  {
    // Get video info
    $video_info = $this->get_video_info();

    // Get video aspect ratio
    $video_aspect_ratio = array();
    foreach ($video_info["streams"] as $stream) {
      if ($stream["codec_type"] == "video") {
        $video_aspect_ratio["display_aspect_ratio"] = isset($stream["display_aspect_ratio"]) ? $stream["display_aspect_ratio"] : "16:9";
        $video_aspect_ratio["sample_aspect_ratio"] = isset($stream["sample_aspect_ratio"]) ? $stream["sample_aspect_ratio"] : "1:1";
        break;
      }
    }
    return $video_aspect_ratio;
  }

  public function is_vertical_video(): bool
  {
    $video_aspect_ratio = $this->get_video_orientation();
    if ($video_aspect_ratio["rotate"] === "0") {
      return false;
    } else {
      return true;
    }
  }

  public function get_video_size(): int
  {
    $video_info = $this->get_video_info();
    return intval($video_info["format"]["size"]);
  }

  public function get_video_information_array(): array
  {
    $video_information_array = array();
    $video_information_array["duration"] = $this->get_video_duration();
    $video_information_array["width"] = $this->get_video_width();
    $video_information_array["height"] = $this->get_video_height();
    $video_information_array["bitrate"] = $this->get_video_bitrate();
    $video_information_array["codec"] = $this->get_video_codec();
    $video_information_array["framerate"] = $this->get_video_framerate();
    $video_information_array["color_parameters"] = $this->get_video_color_parameters();
    $video_information_array["resolution"] = $this->get_video_resolution();
    $video_information_array["orientation"] = $this->get_video_orientation();
    $video_information_array["aspect_ratio"] = $this->get_video_aspect_ratio();
    $video_information_array["is_hdr"] = $this->is_hdr();
    $video_information_array["is_vertical_video"] = $this->is_vertical_video();
    $video_information_array["size"] = $this->get_video_size();
    return $video_information_array;
  }

  public function get_video_information_json(): string
  {
    $video_information_array = $this->get_video_information_array();
    return json_encode($video_information_array, JSON_UNESCAPED_SLASHES);
  }
}

class SubmitVideoTranscodingJob
{
  // Define AWS parameters from environment variables
  private $aws_region;
  private $aws_access_key_id;
  private $aws_secret;
  private $aws_sqs_queue_url_lambda;
  private $aws_sqs_queue_url_fg;
  private $aws_sqs_queue_url = null;
  private $video_file;
  private $sqs_client;
  private $dynamodb_client;

  private $video_info_class;
  private $user_npub;

  public function __construct(string $video_file, string $user_npub = "npub1anonymous")
  {
    // Initialize class variables with environment variables
    $this->aws_region = $_SERVER['AWS_REGION'];
    $this->aws_access_key_id = $_SERVER['AWS_ACCESS_KEY_ID_PROC'];
    $this->aws_secret = $_SERVER['AWS_SECRET_ACCESS_KEY_PROC'];
    $this->aws_sqs_queue_url_lambda = $_SERVER['AWS_SQS_QUEUE_URL_LAMBDA'];
    $this->aws_sqs_queue_url_fg = $_SERVER['AWS_SQS_QUEUE_URL_FG'];

    // Initialize class variables with constructor parameters
    $this->user_npub = $user_npub;
    $this->video_file = $video_file;
    $this->video_info_class = new VideoInformation($this->video_file);

    $sqsConfig = [
      'region' => $this->aws_region,
      'version' => '2012-11-05',
      'credentials' => [
        'key'    => $this->aws_access_key_id,
        'secret' => $this->aws_secret,
      ],
    ];

    $dynamoConfig = [
      'region' => $this->aws_region,
      'version' => '2012-08-10',
      'credentials' => [
        'key'    => $this->aws_access_key_id,
        'secret' => $this->aws_secret,
      ],
    ];

    $this->sqs_client = new SqsClient($sqsConfig);
    $this->dynamodb_client = new DynamoDbClient($dynamoConfig);
  }

  public function submit_video_transcoding_job(string $source_url, array $desired_resolutions): array
  {

    $dynamodb_metadata = $this->prepare_video_ddb_metadata($source_url, $desired_resolutions);

    if (!$this->store_video_information_to_dynamodb($dynamodb_metadata)) {
      throw new Exception("Failed to store video information to DynamoDB");
    }

    $sqs_job_body = $this->prepare_video_sqs_metadata(
      $dynamodb_metadata['videoId'],
      $source_url,
      $desired_resolutions,
      $dynamodb_metadata['desired_codecs'],
      $dynamodb_metadata['duration'],
    );
    $jobId = $this->submit_to_sqs($sqs_job_body);

    $updates = ['jobId' => $jobId, 'mediaStatus' => 'Submitted'];
    if (!$this->update_dynamodb_item($dynamodb_metadata['videoId'], 'video#metadata', $updates)) {
      throw new Exception("Failed to update jobId and status in DynamoDB");
    }

    return $dynamodb_metadata;
  }

  private function prepare_video_sqs_metadata(
    string $videoId,
    string $source_url,
    array $desired_resolutions,
    array $desired_codecs,
    int $duration,
    string $recordType = "video#metadata"
  ): array {
    $jobBody["videoId"] = $videoId;
    $jobBody["recordType"] = $recordType;
    $jobBody["sourceVideoUrl"] = $source_url;
    $jobBody["desiredResolutions"] = $desired_resolutions;
    $jobBody["desiredCodecs"] = $desired_codecs;
    $jobBody["duration"] = $duration;
    return $jobBody;
  }

  private function prepare_video_ddb_metadata(string $source_url, array $desired_resolutions, array $desired_codecs = ["h264"]): array
  {
    // Get video filename and derive video ID
    $filename = basename(parse_url($source_url, PHP_URL_PATH));
    $video_id = getIdFromFilename($filename);
    // Get video mime type
    $video_mime_type = mime_content_type($this->video_file);

    // Add our custom metadata
    $video_metadata_content = $this->video_info_class->get_video_info();
    $video_metadata_content["metadata"]["videoId"] = $video_id;
    $video_metadata_content["metadata"]["is_hdr"] = $this->video_info_class->is_hdr();
    $video_metadata_content["metadata"]["is_vertical_video"] = $this->video_info_class->is_vertical_video();
    $video_metadata_content["metadata"]["orientation"] = $this->video_info_class->get_video_orientation()["rotate"];
    $video_metadata_content["metadata"]["userNpub"] = $this->user_npub;
    $video_metadata_content["metadata"]["mime_type"] = $video_mime_type;
    $video_metadata_content["metadata"]["checksum"] = hash_file("sha256", $this->video_file);
    $video_metadata_content["metadata"]["desired_resolutions"] = $desired_resolutions;
    $video_metadata_content["metadata"]["desired_codecs"] = $desired_codecs;
    $video_metadata_content["metadata"]["source_video_url"] = $source_url;

    // Get ffprobe video information
    $video_metadata_class = new VideoMetadata($video_metadata_content, $filename);
    return $video_metadata_class->getMetadata();
  }

  private function submit_to_sqs(array $video_information_array): string
  {
    $this->choose_sqs_queue();

    $video_information_json = json_encode([], JSON_UNESCAPED_SLASHES);
    $result = $this->sqs_client->sendMessage([
      'MessageBody' => $video_information_json, // REQUIRED, give it an empty array JSON
      'MessageAttributes' => [
        'videoId' => [
          'DataType' => 'String',
          'StringValue' => $video_information_array['videoId']
        ],
        'recordType' => [
          'DataType' => 'String',
          'StringValue' => $video_information_array['recordType']
        ],
        'sourceVideoUrl' => [
          'DataType' => 'String',
          'StringValue' => $video_information_array['sourceVideoUrl']
        ],
        'desiredResolutions' => [
          'DataType' => 'String.Array',
          'StringValue' => json_encode($video_information_array['desiredResolutions'], JSON_UNESCAPED_SLASHES)
        ],
        'desiredCodecs' => [
          'DataType' => 'String.Array',
          'StringValue' => json_encode($video_information_array['desiredCodecs'], JSON_UNESCAPED_SLASHES)
        ],
        'duration' => [
          'DataType' => 'Number.float',
          'StringValue' => $video_information_array['duration']
        ],
        'userNpub' => [
          'DataType' => 'String',
          'StringValue' => $this->user_npub ?? 'npub1anonymous'
        ],
      ],
      'QueueUrl' => $this->aws_sqs_queue_url,
      'MessageGroupId' => 'VideoTranscoding',
      'MessageDeduplicationId' => md5($video_information_json),
    ]);

    if (!isset($result["MessageId"]) || $result["MessageId"] === null) {
      throw new Exception("Failed to submit video transcoding job");
    } else {
      error_log("Submitted video transcoding job: " . $result["MessageId"]);
    }
    return $result["MessageId"];
  }

  private function choose_sqs_queue(): void
  {
    $video_duration = $this->video_info_class->get_video_duration();
    $video_is_hdr = $this->video_info_class->is_hdr();
    $video_size = $this->video_info_class->get_video_size();

    $this->aws_sqs_queue_url = ($video_size < 1024 ** 2 * 100 || ($video_duration < 90 && $video_is_hdr === false)) ? $this->aws_sqs_queue_url_lambda : $this->aws_sqs_queue_url_fg;
  }

  private function store_video_information_to_dynamodb(array $content, string $table = 'VideoCatalog'): bool
  {
    $marshaler = new Marshaler();


    try {
      $item = $marshaler->marshalItem($content);
      $res = $this->dynamodb_client->putItem([
        'TableName' => $table,
        'Item' => $item
      ]);
    } catch (DynamoDbException $e) {
      error_log($e->getMessage());
      return false;
    }

    return $res['@metadata']['statusCode'] === 200;
  }

  private function update_dynamodb_item(string $pkValue, string $skValue, array $updates, string $table = 'VideoCatalog'): bool
  {
    $updateExpressions = [];
    $expressionAttributeNames = [];
    $expressionAttributeValues = [];

    foreach ($updates as $field => $value) {
      $updateExpressions[] = "#{$field} = :{$field}";
      $expressionAttributeNames["#{$field}"] = $field;
      $expressionAttributeValues[":{$field}"] = ['S' => (string)$value];
    }

    try {
      $result = $this->dynamodb_client->updateItem([
        'TableName' => $table,
        'Key' => [
          'videoId' => ['S' => $pkValue],
          'recordType' => ['S' => $skValue]
        ],
        'UpdateExpression' => 'SET ' . implode(', ', $updateExpressions),
        'ExpressionAttributeNames' => $expressionAttributeNames,
        'ExpressionAttributeValues' => $expressionAttributeValues
      ]);

      return $result['@metadata']['statusCode'] === 200;
    } catch (DynamoDbException $e) {
      error_log($e->getMessage());
      return false;
    }
  }
}

class VideoMetadata
{
  private $data;
  private $videoId;
  private $fileName;

  public function __construct(array $data, string $fileName)
  {
    // FFPROBE output array with custom video metadata
    $this->data = $data;
    $this->videoId = $this->data['metadata']['videoId'] ?? getIdFromFilename($fileName);
    $this->fileName = $fileName;
  }

  public function getMetadata()
  {
    $videoStream = $this->getStreamData('video');
    $audioStream = $this->getStreamData('audio');

    // createdAt: timestamp when the video was submitted
    // updatedAt: timestamp when the video was updated
    // filename: original filename (either sha256 of the file content or hashids generated name)
    // duration: duration of the video in seconds (float)
    // originalMimeType: mime type of the video
    /*

    'variantVideos' => [
      [
          'url' => 'http://example.com/video_1080p.mp4',
          'resolution' => '1080p',
          'codec' => 'h264',
          'profile' => 'high',
          'level' => '4.0',
          'bitrate' => 5000000,
          'framerate' => '30/1',
          'duration' => 120.0,
          'size' => 100000000,
          'checksum' => 'sha256:...',
          'isHdr' => false,
          'pixelFormat' => 'yuv420p',
          'isVerticalVideo' => false,
          'orientation' => 0,
          'mimeType' => 'video/mp4',
          'audioCodec' => 'aac',
          'audioSampleRate' => 48000,
          'audioChannels' => 2,
          'audioChannelLayout' => 'stereo',
          'audioBitRate' => 128000,
      ],
      // ... more variants
    ]
    */

    return [
      // General Info
      'videoId' => $this->videoId, // Unique identifier for the video
      'recordType' => 'video#metadata', // Record type
      'createdAt' => date('Y-m-d H:i:s'), // Timestamp when the video was submitted
      'updatedAt' => date('Y-m-d H:i:s'), // Timestamp when the video was last updated
      'userNpub' => $this->data['metadata']['userNpub'] ?? 'npub1anonymous', // User's npub or default if not available
      'checksum' => $this->data['metadata']['checksum'] ?? '', // SHA256 hash of the original video file
      'filename' => $this->fileName ?? '', // Original filename of the video
      'size' => $this->data['format']['size'] ?? 0, // Size of the video file in bytes
      'duration' => $this->data['format']['duration'] ?? 0, // Total duration of the video in seconds
      // Descriptive Info
      'title' => $this->data['metadata']['title'] ?? '', // Title of the video
      'description' => $this->data['metadata']['description'] ?? '', // Description or synopsis of the video
      'album' => $this->data['metadata']['album'] ?? '', // Album or collection the video belongs to
      'tags' => $this->data['metadata']['tags'] ?? [], // Tags associated with the video
      'license' => $this->data['metadata']['license'] ?? 'Public Domain', // Licensing information of the video
      'copyright' => $this->data['metadata']['copyright'] ?? '', // Copyright information for the video
      'nsfw' => $this->data['metadata']['nsfw'] ?? false, // Flag indicating if the content is not safe for work
      'category' => $this->data['metadata']['category'] ?? 'Miscellaneous Video', // Category to which the video belongs
      'language' => $this->data['metadata']['language'] ?? 'English', // Language of the video content
      'location' => $this->data['metadata']['location'] ?? '', // Geographical location related to the video content
      // Video Permissions
      'allowComments' => $this->data['metadata']['allowComments'] ?? true, // Permission to comment on the video
      'allowEmbedding' => $this->data['metadata']['allowEmbedding'] ?? true, // Permission to embed the video
      'allowRating' => $this->data['metadata']['allowRating'] ?? true, // Permission to rate the video
      'allowSharing' => $this->data['metadata']['allowSharing'] ?? true, // Permission to share the video
      'allowDownload' => $this->data['metadata']['allowDownload'] ?? true, // Permission to download the video
      'privacy' => $this->data['metadata']['privacy'] ?? 'private', // Video's privacy setting (e.g., public, private)
      // Generated Video Files info
      'subtitlesUrl' => $this->data['metadata']['subtitles'] ?? '', // URL to subtitles file
      'posterUrl' => $this->data['metadata']['poster'] ?? '', // URL to a poster image
      'animatedPosterUrl' => $this->data['metadata']['animatedPoster'] ?? '', // URL to an animated poster/gif
      'storyboardUrl' => $this->data['metadata']['storyboard'] ?? '', // URL to the video storyboard
      'storyboardVttUrl' => $this->data['metadata']['storyboardVtt'] ?? '', // URL to a VTT file associated with the storyboard
      'defaultVideoUrl' => $this->data['metadata']['video'] ?? '', // URL to the video file
      'variantVideos' => $this->data['metadata']['variantVideos'] ?? [], // List of generated video files with metadata
      'videoUrlHls' => $this->data['metadata']['videoHls'] ?? '', // URL to the HLS stream of the video
      'videoUrlDash' => $this->data['metadata']['videoDash'] ?? '', // URL to the DASH stream of the video
      // Video stream
      'chapters' => $this->data['chapters'] ?? [], // Chapters or segments within the video
      'width' => $videoStream['width'] ?? 0, // Width of the video in pixels
      'height' => $videoStream['height'] ?? 0, // Height of the video in pixels
      'avgFramerate' => $videoStream['avg_frame_rate'] ?? '', // Average framerate of the video
      'framerate' => $videoStream['r_frame_rate'] ?? '', // Framerate of the video
      'framesCount' => $videoStream['nb_frames'] ?? 0, // Total number of frames in the video
      'pixelFormat' => $videoStream['pix_fmt'] ?? 'yuv420p', // Pixel format used in the video
      'colorPrimaries' => $videoStream['color_primaries'] ?? 'bt709', // Color primaries used in the video
      'colorTransfer' => $videoStream['color_transfer'] ?? 'bt709', // Color transfer used in the video
      'colorSpace' => $videoStream['color_space'] ?? 'bt709', // Color space used in the video
      'colorRange' => $videoStream['color_range'] ?? 'tv', // Color range used in the video
      'format_name' => $this->data['format']['format_long_name'] ?? '', // Format or container of the video
      'bit_rate' => $this->data['format']['bit_rate'] ?? 0, // Bitrate of the video
      'encoder' => $this->data['format']['tags']['encoder'] ?? '', // Software or library used to encode the video
      'videoCodec' => $videoStream['codec_name'] ?? '', // Video codec used (e.g., h.264, VP9)
      'videoProfile' => $videoStream['profile'] ?? '', // Profile of the video codec (e.g., baseline, main)
      'videoLevel' => $videoStream['level'] ?? '', // Level of the video codec
      'displayAspectRatio' => $videoStream['display_aspect_ratio'] ?? '', // Display aspect ratio of the video (e.g., 16:9)
      'videoBitRate' => $videoStream['bit_rate'] ?? 0, // Bitrate of the video stream
      'videoDuration' => $videoStream['duration'] ?? 0, // Duration of the video stream in seconds
      'isHdr' => $this->data['metadata']['is_hdr'] ?? false, // Flag indicating if the video is in HDR
      'isVerticalVideo' => $this->data['metadata']['is_vertical_video'] ?? false, // Flag indicating if the video is vertical
      'orientation' => $this->data['metadata']['orientation'] ?? 0, // Orientation of the video (0, 90, 180, 270 degrees)
      'originalMimeType' => $this->data['metadata']['mime_type'] ?? 'application/octet-stream', // MIME type of the video
      // Audio stream
      'audioCodec' => $audioStream['codec_name'] ?? '', // Audio codec used (e.g., AAC, Opus)
      'audioSampleRate' => $audioStream['sample_rate'] ?? 0, // Sample rate of the audio in Hz
      'audioChannels' => $audioStream['channels'] ?? 0, // Number of audio channels
      'audioChannelLayout' => $audioStream['channel_layout'] ?? '', // Channel layout of the audio (e.g., stereo, 5.1)
      'audioBitRate' => $audioStream['bit_rate'] ?? 0, // Bitrate of the audio stream
      'audioDuration' => $audioStream['duration'] ?? 0, // Duration of the audio stream in seconds
      // SQS
      'mediaStatus' => 'Pending', // Current processing status of the video. Values: Pending, Submitted, Processing, Completed, Failed
      'sourceVideoUrl' => $this->data['metadata']['source_video_url'] ?? '', // URL to the original video file
      'desiredCodecs' => $this->data['metadata']['desired_codecs'] ?? [], // Desired codecs for transcoding
      'desiredResolutions' => $this->data['metadata']['desired_resolutions'] ?? [], // Desired resolutions for transcoding
      'jobId' => $this->data['metadata']['jobId'] ?? '', // Job ID of the video transcoding job
    ];
  }

  private function getStreamData($type)
  {
    foreach ($this->data['streams'] as $stream) {
      if ($stream['codec_type'] === $type) {
        return $stream;
      }
    }
    return [];
  }
}
