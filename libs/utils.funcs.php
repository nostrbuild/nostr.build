<?php
// Assign file types
function getFileType(string $ext): string
{
  $fileTypes = [
    'image' => ['jpg', 'jpeg', 'png', 'apng', 'gif', 'webp', 'bmp', 'tiff', 'heic', 'heif', 'avif', 'jp2', 'jpx', 'jpm', 'jxr', 'jfif', 'ico'],
    'audio' => ['mp3', 'ogg', 'wav', 'aac', 'webm', 'flac', 'aif', 'wma', 'm4a', 'm4b', 'm4p', 'm4r', 'm4v', 'mp2', 'mpa', 'mpga', 'mp4a', 'mpga', 'mpg', 'mpv2', 'mp2v', 'mpe', 'm2a', 'm2v', 'm2s', 'm2t', 'm2ts', 'm2v', 'm3a'],
    'video' => ['mp4', 'webm', 'ogv', 'avi', 'wmv', 'mov', 'mpeg', '3gp', '3g2', 'flv', 'm4v', 'mkv', 'mpg', 'm2v', 'm4p', 'm4v', 'mp2', 'mpa', 'mpe', 'mpv', 'm2ts', 'mts', 'ts', 'mxf', 'asf', 'rm', 'rmvb', 'vob', 'f4v', 'm2v', 'm2ts', 'mts', 'ts', 'mxf', 'asf', 'rm', 'rmvb', 'vob', 'f4v'],
    'archive' => ['zip', 'tar', 'gz', 'bz2', 'xz', 'lz', 'tar.gz', 'tar.bz', 'tar.xz'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'],
    'text' => ['txt', 'html', 'css', 'js', 'json', 'xml', 'yaml', 'toml', 'md', 'tex', 'rst', 'adoc', 'org', 'texinfo', 'roff'],
    'other' => ['svg', 'epub', 'mobi', 'psd'],
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

// Convenience function to get file type from the full name
function getFileTypeFromName(string $filename): string
{
  $ext = pathinfo($filename, PATHINFO_EXTENSION);
  return getFileType($ext);
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

function getAllowedMimesArray(int $acctlevel = 0): array
{
  // Sync with JS Uppy config

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
    'audio/x-m4a' => 'm4a', // M4A audio
    'audio/x-m4b' => 'm4b', // M4B audio
    'audio/mp4' => 'mp4a', // MP4 audio
    'audio/mpegurl' => 'm3u', // M3U audio playlist
    'audio/x-mpegurl' => 'm3u', // M3U audio playlist
    'audio/x-ms-wax' => 'wax', // Windows Media Audio Redirector (WAX)
    'audio/x-realaudio' => 'ra', // RealAudio
    'audio/x-pn-realaudio' => 'ram', // RealAudio Metadata
    'audio/x-pn-realaudio-plugin' => 'rmp', // RealAudio Plugin
    'audio/x-wav' => 'wav', // Waveform Audio File Format (WAV)

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
    'video/x-matroska' => 'mkv', // Matroska Multimedia Container (MKV)
    'video/x-mpeg2' => 'mp2v', // MPEG-2 video
    'video/x-m4p' => 'm4p', // M4P video
    'video/mp2t' => 'm2ts', // MPEG-2 transport stream
    'video/MP2T' => 'ts', // MPEG-2 transport stream
    'video/mp2p' => 'mp2', // MPEG-2 Program Stream
    'video/x-mxf' => 'mxf', // Material Exchange Format (MXF)
    'video/x-ms-asf' => 'asf', // Advanced Systems Format (ASF)
    'video/x-ms-wm' => 'asf', // Advanced Systems Format (ASF)
    'video/x-pn-realvideo' => 'rm', // RealVideo
    'video/x-ms-vob' => 'vob', // DVD Video Object (VOB)
    'video/x-f4v' => 'f4v', // Flash Video (F4V)
    'video/x-fli' => 'fli', // FLIC video
    'video/x-m2v' => 'm2v', // MPEG-2 video
    'video/x-ms-wmx' => 'wmx', // Windows Media Video Redirector (WMX)
    'video/x-ms-wvx' => 'wvx', // Windows Media Video Playlist (WVX)
    'video/x-sgi-movie' => 'movie', // Silicon Graphics movie
  ];

  $mimeTypesAddonDocs = [
    // Documents
    'application/pdf' => 'pdf', // Portable Document Format (PDF)

    // SVG, for now...
    'image/svg+xml' => 'svg', // SVG vector image
  ];

  $mimeTypesAddonExtra = [
    // Archives
    'application/zip' => 'zip', // ZIP archive
    'application/tar' => 'tar', // Tarball archive
  ];

  // Merge the arrays based on account level
  if ($acctlevel === 10 /* Advanced */ || $acctlevel == 99 /* Admin */ || $acctlevel === 1 /* Creator */) {
    $mimeTypes = array_merge($mimeTypes, $mimeTypesAddonDocs, $mimeTypesAddonExtra);
  } elseif ($acctlevel === 2 /* Pro */) {
    $mimeTypes = array_merge($mimeTypes, $mimeTypesAddonDocs);
  }

  return $mimeTypes;
}

// Function to detect file format based on extension and mime type
function detectFileExt($file, int $acctlevel = 0)
{
  // Try to get the extension from the file name
  // $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

  // Get the MIME type
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $file);
  finfo_close($finfo);
  // DEBUG
  error_log("\nMIME type: $mimeType\n");

  // Map MIME types to extensions and file types
  $mimeTypes = getAllowedMimesArray($acctlevel);

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

// Replacement for uniqid which is prone to collisions
function generateUniqueFilename($prefix, $tempDirectory = null)
{
  do {
    $crypto_str = bin2hex(random_bytes(32)); // Generates a 64-character long hex string.
    $filename = $prefix . $crypto_str;

    // Check if tempDirectory is provided and if the file already exists.
    $tempFilePath = $tempDirectory ? $tempDirectory . '/' . $filename : $filename;
  } while ($tempDirectory && file_exists($tempFilePath));

  return $tempFilePath;
}

function getIdFromFilename($filename)
{
  $filename_base = pathinfo($filename, PATHINFO_FILENAME);
  return preg_match('/^[a-f0-9]{64}$/i', $filename_base) === 1 ?
    $filename_base :
    hash('sha256', $filename_base);
}

/**
 * Hash a password using PBKDF2 and return a combined string of the Base64-encoded salt and hash.
 * 
 * @param string $password The password to hash.
 * @return string A combined string of the Base64-encoded salt and hash.
 */
function hashPasswordPBKDF2(string $password): string
{
  $salt = random_bytes(16);
  $hash = hash_pbkdf2("sha256", $password, $salt, 100000, 0, true);
  $base64Salt = base64_encode($salt);
  $base64Hash = base64_encode($hash);
  return $base64Salt . ':' . $base64Hash;
}

/**
 * Verify a password against a stored hash.
 * 
 * @param string $password The password to verify.
 * @param string $storedCombined The stored combined string of Base64-encoded salt and hash.
 * @return bool True if the password matches, false otherwise.
 */
function verifyPasswordPBKDF2(string $password, string $storedCombined): bool
{
  [$base64Salt, $base64Hash] = explode(':', $storedCombined);
  $salt = base64_decode($base64Salt);
  $hash = base64_decode($base64Hash);
  $computedHash = hash_pbkdf2("sha256", $password, $salt, 100000, 0, true);
  return hash_equals($computedHash, $hash);
}
