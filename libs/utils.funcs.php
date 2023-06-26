<?php
// Assign file types
function getFileType(string $ext): string
{
  $fileTypes = [
    'image' => ['jpg', 'png', 'apng', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'psd', 'heic', 'heif', 'avif', 'jp2', 'jpx', 'jpm', 'jxr', 'jfif', 'ico'],
    'audio' => ['mp3', 'ogg', 'wav', 'aac', 'webm', 'flac', 'aif', 'wma'],
    'video' => ['mp4', 'webm', 'ogv', 'avi', 'wmv', 'mov', 'mpeg', '3gp', '3g2', 'flv', 'm4v']
  ];
  foreach ($fileTypes as $type => $extensions) {
    if (in_array($ext, $extensions)) {
      $fileType = $type;
      break;
    } else {
      $fileType = 'unknown';
    }
  }
  return $fileType;
}

function formatSizeUnits($bytes)
{
  if ($bytes >= 1073741824) {
    $bytes = number_format($bytes / 1073741824, 2) . ' GB';
  } elseif ($bytes >= 1048576) {
    $bytes = number_format($bytes / 1048576, 2) . ' MB';
  } elseif ($bytes >= 1024) {
    $bytes = number_format($bytes / 1024, 2) . ' KB';
  } elseif ($bytes > 1) {
    $bytes = $bytes . ' bytes';
  } elseif ($bytes == 1) {
    $bytes = $bytes . ' byte';
  } else {
    $bytes = '0 bytes';
  }

  return $bytes;
}

// Function to detect file format based on extension and mime type
function detectFileExt($file)
{
  // Try to get the extension from the file name
  // $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

  // Get the MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file);
  finfo_close($finfo);

  // Map MIME types to extensions and file types
  $mimeTypes = [
    // Images
    'image/jpeg' => 'jpg', // JPEG image
    'image/png' => 'png', // PNG image
    'image/apng' => 'apng', // Animated Portable Network Graphics (APNG) image
    'image/gif' => 'gif', // GIF image
    'image/webp' => 'webp', // WebP image
    //'image/svg+xml' => 'svg', // SVG vector image
    'image/bmp' => 'bmp', // Bitmap image
    'image/tiff' => 'tiff', // TIFF image
    //'image/vnd.adobe.photoshop' => 'psd', // Adobe Photoshop Document
    //'image/heic' => 'heic', // High Efficiency Image Format (HEIC)
    //'image/heif' => 'heif', // High Efficiency Image File Format (HEIF)
    'image/avif' => 'avif', // AV1 Image File Format (AVIF)
    //'image/jp2' => 'jp2', // JPEG 2000 image
    //'image/jpx' => 'jpx', // JPEG 2000 Part 2 image
    //'image/jpm' => 'jpm', // JPEG 2000 Part 6 (Compound) image
    //'image/jxr' => 'jxr', // JPEG XR image
    'image/pipeg' => 'jfif', // JPEG File Interchange Format (JFIF)
    //'image/x-icon' => 'ico', // Icon format
    //'image/vnd.microsoft.icon' => 'ico', // Microsoft Icon format

    // Audio
    'audio/mpeg' => 'mp3', // MP3 audio
    'audio/ogg' => 'ogg', // Ogg Vorbis audio
    'audio/wav' => 'wav', // Waveform Audio File Format (WAV)
    'audio/aac' => 'aac', // Advanced Audio Coding (AAC) audio
    'audio/webm' => 'webm', // WebM audio
    'audio/flac' => 'flac', // Free Lossless Audio Codec (FLAC)
    'audio/x-aiff' => 'aif', // Audio Interchange File Format (AIFF)
    'audio/x-ms-wma' => 'wma', // Windows Media Audio (WMA)

    // Video
    'video/mp4' => 'mp4', // MP4 video
    'video/webm' => 'webm', // WebM video
    'video/ogg' => 'ogv', // Ogg Theora video
    'video/x-msvideo' => 'avi', // Audio Video Interleave (AVI)
    'video/x-ms-wmv' => 'wmv', // Windows Media Video (WMV)
    'video/quicktime' => 'mov', // QuickTime video
    'video/mpeg' => 'mpeg', // MPEG video
    'video/3gpp' => '3gp', // 3GPP mobile video
    'video/3gpp2' => '3g2', // 3GPP2 mobile video
    'video/x-flv' => 'flv', // Flash Video (FLV)
    'video/x-m4v' => 'm4v', // M4V video
  ];

  if (!isset($mimeTypes[$mimeType])) {
    // It's probably best to throw exception here then try and salvage situation based on supplied extension
    throw new InvalidArgumentException("Unknown or unsupported file type");
  }

  $fileExtension = $mimeTypes[$mimeType];

  $fileType = getFileType($fileExtension);

  if (!isset($fileType)) {
    throw new InvalidArgumentException("Unknown or unsupported file type");
  }

  return ['type' => $fileType, 'extension' => $fileExtension, 'mime' => $mimeType];
}
