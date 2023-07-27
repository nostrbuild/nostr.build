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
    'image/heic' => 'heic', // High Efficiency Image Format (HEIC)
    //'image/heif' => 'heif', // High Efficiency Image File Format (HEIF)
    'image/avif' => 'avif', // AV1 Image File Format (AVIF)
    'image/jp2' => 'jp2', // JPEG 2000 image
    'image/jpx' => 'jpx', // JPEG 2000 Part 2 image
    'image/jpm' => 'jpm', // JPEG 2000 Part 6 (Compound) image
    'image/jxr' => 'jxr', // JPEG XR image
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

// Function to check URL sanity
function checkUrlSanity(string $url): bool
{
  $parsedUrl = parse_url($url);

  // Checking if URL is valid
  if ($parsedUrl === false) {
    throw new InvalidArgumentException('Invalid URL');
  }

  // Checking for private IPs and localhost
  $hostname = $parsedUrl['host'] ?? '';

  // Checking for valid scheme
  if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
    throw new InvalidArgumentException('Only HTTP and HTTPS schemes are allowed');
  }

  // Checking for private IPs and localhost
  if (
    filter_var($hostname, FILTER_VALIDATE_IP) !== false &&
    filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
  ) {
    throw new InvalidArgumentException('Access to the private IP ranges or localhost is not allowed');
  }

  // Checking for AWS metadata IP
  if ($hostname === '169.254.169.254') {
    throw new InvalidArgumentException('Access to the AWS metadata IP is not allowed');
  }

  // Checking for AWS EC2-like hostnames
  if (preg_match('/^ip-\d{1,3}-\d{1,3}-\d{1,3}-\d{1,3}$/', $hostname)) {
    throw new InvalidArgumentException('AWS EC2-like hostnames are not allowed');
  }

  // Perform DNS lookup
  $ips = gethostbynamel($hostname);

  // If the hostname could not be resolved, skip the check
  if ($ips !== false) {
    foreach ($ips as $ip) {
      if (
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
      ) {
        throw new InvalidArgumentException('DNS rebinding attacks are not allowed');
      }
    }
  }

  // Checking for AWS TLDs
  if (preg_match("/\.aws$/", $hostname)) {
    throw new InvalidArgumentException('AWS TLDs are not allowed');
  }

  // Checking for localhost
  if ($hostname === 'localhost') {
    throw new InvalidArgumentException('Access to localhost is not allowed');
  }

  // Checking for special-use domain names
  $specialDomains = ['test', 'example', 'invalid', 'localhost', 'local'];
  foreach ($specialDomains as $domain) {
    if (strpos($hostname, $domain) !== false) {
      throw new InvalidArgumentException("Access to the special-use domain '{$domain}' is not allowed");
    }
  }

  // Checking for internal TLDs
  $internalTlds = ['internal', 'corp', 'home', 'lan'];
  foreach ($internalTlds as $tld) {
    if (preg_match("/\b{$tld}$/", $hostname)) {
      throw new InvalidArgumentException("Access to the internal TLD '{$tld}' is not allowed");
    }
  }

  // Checking for server IP
  if ($hostname === $_SERVER['SERVER_ADDR']) {
    throw new InvalidArgumentException('Access to the server\'s IP address is not allowed');
  }

  // Checking for server hostname
  if ($hostname === $_SERVER['SERVER_NAME']) {
    throw new InvalidArgumentException('Access to the server\'s hostname is not allowed');
  }

  // Checking for server domain
  $serverDomain = substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], '.') + 1);
  if (strpos($hostname, $serverDomain) !== false) {
    throw new InvalidArgumentException('Access to the server\'s domain name is not allowed');
  }

  // Checking for http host
  $httpHostDomain = substr($_SERVER['HTTP_HOST'], strpos($_SERVER['HTTP_HOST'], '.') + 1);
  if (strpos($hostname, $httpHostDomain) !== false) {
    throw new InvalidArgumentException('Access to the HTTP host\'s domain is not allowed');
  }

  // Checking for non-standard ports
  if (isset($parsedUrl['port']) && !in_array($parsedUrl['port'], [80, 443])) {
    throw new InvalidArgumentException('Access to non-standard ports is not allowed');
  }

  // Checking for username and password in URL
  if (isset($parsedUrl['user']) || isset($parsedUrl['pass'])) {
    throw new InvalidArgumentException('URLs with username and password specs are not allowed');
  }

  return true;
}
