<?php

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Summary of btcpayWebhookResponse
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $code
 * @param string $message
 * @return Psr\Http\Message\ResponseInterface
 */
function btcpayWebhookResponse(Response $response, string $code, string $message): Response
{
  $responseBody = [
    'code' => $code,
    'message' => $message,
  ];
  $response->getBody()->write(json_encode($responseBody));
  return $response->withHeader('Content-Type', 'application/json');
}

/**
 * Summary of jsonResponse
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $status
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 * @return Psr\Http\Message\ResponseInterface
 * Utility function to return a JSON response
 */
function jsonResponse(Response $response, string $status, string $message, $data, int $statusCode = 200): Response
{
  $responseBody = [
    'status' => $status,
    'message' => $message,
    'data' => $data,
  ];
  $response->getBody()->write(json_encode($responseBody));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

/**
 * Summary of uppyResponse
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $status
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 * @return Psr\Http\Message\ResponseInterface
 */
function uppyResponse(Response $response, string $status, string $message, $data, int $statusCode = 200): Response
{
  $responseBody = ($status === 'success') ? createSuccessResponse($data) : createErrorResponse($message);

  $response->getBody()->write(json_encode($responseBody));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

/**
 * Summary of createSuccessResponse
 * @param mixed $data
 * @return array
 */
function createSuccessResponse($data): array
{
  if (!is_array($data)) {
    return [];
  }

  $resolutionToWidth = [
    "240p"  => "426",
    "360p"  => "640",
    "480p"  => "854",
    "720p"  => "1280",
    "1080p" => "1920",
  ];
  $tmp = [];
  foreach ($data as $file) {
    // Construct file object to be used in the response for the interface update
    // Construct returned data

    $fileData = [
      "id" => $file['id'],
      "flag" => 0, // Not shared by default
      "name" => $file['name'],
      "mime" => $file['mime'],
      "url" => $file['url'],
      "thumb" => $file['thumbnail'],
      "responsive" => $file['responsive'],
      "size" => $file['size'],
      "sizes" => '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw',
      "srcset" => implode(", ", array_map(function ($resolution) use ($file, $resolutionToWidth) {
        return htmlspecialchars($file['responsive'][$resolution] . " {$resolutionToWidth[$resolution]}w");
      }, array_keys($file['responsive']))),
      "width" => $file['dimensions']['width'] ?? 0,
      "height" => $file['dimensions']['height'] ?? 0,
      "media_type" => $file['media_type'],
      "blurhash" => $file['blurhash'],
      "sha256_hash" => $file['original_sha256'],
      "created_at" => date('Y-m-d H:i:s'),
      "title" => $file['title'] ?? '',
      "ai_prompt" => $file['ai_prompt'] ?? '',
      "description" => '',
      "loaded" => false,
      "show" => true,
      "associated_notes" => null,
    ];

    $tmp[] = [
      "id" => $file['sha256'],
      "url" => $file['url'],
      "name" => $file['name'],
      "type" => $file['mime'],
      "size" => $file['size'],
      "fileData" => $fileData,
    ];
  }
  return count($tmp) == 1 ? $tmp[0] : $tmp;
}

/**
 * Summary of createErrorResponse
 * @param string $message
 * @return array
 */
function createErrorResponse(string $message): array
{
  return [
    "error" => $message,
  ];
}

// NIP-96 handling
/*
{
  // "success" if successful or "error" if not
  status: "success",
  // Free text success, failure or info message
  message: "Upload successful.",
  // Optional. See "Delayed Processing" section
  processing_url: "...",
  // This uses the NIP-94 event format but DO NOT need
  // to fill some fields like "id", "pubkey", "created_at" and "sig"
  //
  // This holds the download url ("url"),
  // the ORIGINAL file hash before server transformations ("ox")
  // and, optionally, all file metadata the server wants to make available
  //
  // nip94_event field is absent if unsuccessful upload
  nip94_event: {
    // Required tags: "url" and "ox"
    tags: [
      // Can be same from /.well-known/nostr/nip96.json's "download_url" field
      // (or "api_url" field if "download_url" is absent or empty) with appended
      // original file hash.
      //
      // Note we appended .png file extension to the `x` value
      // (it is optional but extremely recommended to add the extension as it will help nostr clients
      // with detecting the file type by using regular expression)
      //
      // Could also be any url to download the file
      // (using or not using the /.well-known/nostr/nip96.json's "download_url" prefix),
      // for load balancing purposes for example.
      ["url", "https://your-file-server.example/custom-api-path/719171db19525d9d08dd69cb716a18158a249b7b3b3ec4bbdec5698dca104b7b.png"],
      // SHA-256 hash of the ORIGINAL file, before transformations.
      // The server MUST store it even though it represents the ORIGINAL file because
      // users may try to download the transformed file using this value
      [
        "ox",
        "719171db19525d9d08dd69cb716a18158a249b7b3b3ec4bbdec5698dca104b7b",
        // Server hostname where one can find the
        // /.well-known/nostr/nip96.json config resource.
        //
        // This value is an important hint that clients can use
        // to find new NIP-96 compatible file storage servers.
        "https://your-file-server.example"
      ],
      // Optional. SHA-256 hash of the saved file after any server transformations.
      // The server can but does not need to store this value.
      ["x", "543244319525d9d08dd69cb716a18158a249b7b3b3ec4bbde5435543acb34443"],
      // Optional. Recommended for helping clients to easily know file type before downloading it.
      ["m", "image/png"]
      // Optional. Recommended for helping clients to reserve an adequate UI space to show the file before downloading it.
      ["dim", "800x600"]
      // Blurhash
      ["bh", "LKO2?U%2Tw=w]~RBVZRi};RPxuwH"],
      // ... other optional NIP-94 tags
    ],
    content: ""
  },
  // ... other custom fields (please consider adding them to this NIP or to NIP-94 tags)
  metadata: [
    thumbnail: "https://example.com/thumbnail.png", // for image types (optional)
    size: "123456", // for all media types (in bytes)
    duration: "123.456", // for video and audio types (in seconds)
    poster: "https://example.com/poster.png", // for video and audio types (optional)
    animated_poster: "https://example.com/animated_poster.mp4(webp, gif)", // for video types (optional)
    hls_stream: "https://example.com/stream.m3u8", // for video and audio types (optional)
    storyboard_vtt: "https://example.com/storyboard.vtt", // for video types (optional)
    alternative_formats: [ // for all types (optional)
      {
        mime: "video/mp4",
        dimensions: "800x600",
        codec: "h264",
        url: "https://example.com/video.mp4"
      },
      {
        mime: "video/webm",
        dimensions: "800x600",
        codec: "vp9",
        url: "https://example.com/video.webm"
      }]
  ]
}
*/

/**
 * Summary of nip96Response
 * @param Psr\Http\Message\ResponseInterface $response
 * @param string $status
 * @param string $message
 * @param mixed $data
 * @param string $processing_url
 * @param int $statusCode
 * @return Psr\Http\Message\ResponseInterface
 */
function nip96Response(Response $response, string $status, string $message, $data, ?string $processing_url = null, int $statusCode = 200): Response
{
  $responseBody = ($status === 'success')
    ? createNip96SuccessResponse($data, $message, $processing_url)
    : createNip96ErrorResponse($message);

  error_log('nip96Response: ' . json_encode($responseBody, JSON_UNESCAPED_SLASHES));
  $response->getBody()->write(json_encode($responseBody, JSON_UNESCAPED_SLASHES));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

/**
 * Summary of createNip96SuccessResponse
 * @param array $data
 * @param string $message
 * @param string $processing_url
 * @return array
 */
function createNip96SuccessResponse(array $data, string $message, ?string $processing_url = null): array
{
  if (!is_array($data)) {
    return [];
  }

  // Set core NIP-94 event fields
  $nip94_event = [];
  if (isset($data['url']) || isset($data['original_sha256'])) {
    $nip94_event = [
      'tags' => [
        ["url", $data['url']],
        ["ox", $data['original_sha256']]
      ],
      'content' => "" // Empty by design
    ];
  }

  if(isset($data['fallback'])) {
    $nip94_event['tags'][] = ["fallback", $data['fallback']];
  }

  if (isset($data['sha256']) || isset($data['original_sha256'])) {
    $nip94_event['tags'][] = ["x", $data['sha256'] ?? $data['original_sha256']];
  }

  if (isset($data['mime'])) {
    $nip94_event['tags'][] = ["m", $data['mime']];
  }

  if (isset($data['dimensionsString'])) {
    $nip94_event['tags'][] = ["dim", $data['dimensionsString']];
  }

  if (isset($data['blurhash'])) {
    $nip94_event['tags'][] = ["bh", $data['blurhash']];
  }

  if (isset($data['blurhash'])) {
    $nip94_event['tags'][] = ["blurhash", $data['blurhash']];
  }

  if (isset($data['thumbnail'])) {
    $nip94_event['tags'][] = ["thumb", $data['thumbnail']];
  }

  $metadata = [];

  if (isset($data['size'])) {
    $metadata['size'] = $data['size'];
  }

  if (isset($data['duration'])) {
    $metadata['duration'] = $data['duration'];
  }

  if (isset($data['thumbnail'])) {
    $metadata['thumbnail'] = $data['thumbnail'];
  }

  if (isset($data['poster'])) {
    $metadata['poster'] = $data['poster'];
  }

  if (isset($data['animated_poster'])) {
    $metadata['animated_poster'] = $data['animated_poster'];
  }

  if (isset($data['hls_stream'])) {
    $metadata['hls_stream'] = $data['hls_stream'];
  }

  if (isset($data['storyboard_vtt'])) {
    $metadata['storyboard_vtt'] = $data['storyboard_vtt'];
  }

  if (isset($data['alternative_formats'])) {
    $metadata['alternative_formats'] = $data['alternative_formats'];
  }

  // Add other optional metadata fields if they exist...

  $response = [
    'status' => 'success',
    'message' => $message,
  ];

  if (!empty($nip94_event)) {
    $response['nip94_event'] = $nip94_event;
  }

  if (!empty($metadata)) {
    $response['metadata'] = $metadata;
  }

  if ($processing_url) {
    $response['processing_url'] = $processing_url;
  }

  return $response;
}

/**
 * Summary of createNip96ErrorResponse
 * @param string $message
 * @return array
 */
function createNip96ErrorResponse(string $message): array
{
  return [
    'status' => 'error',
    'message' => $message
  ];
}

/**
 * Blossom API Helper Functions
 */

// function to generate a cryptographically secure random string in base 64 format and URL safe, 3 characters long
function generateRandomString(): string
{
    return substr(rtrim(strtr(base64_encode(random_bytes(2)), '+/', '-_'), '='), 0, 3);
}

// function to convert all x-<header name> to assoc array of <header name> (without x-) and first value
function metadataFromHeaders(array $headers): array
{
    $metadata = [];
    foreach ($headers as $key => $values) {
        // Normalize header name to lowercase
        $normalizedKey = strtolower($key);

        // Extract Content-Type to content_type and first value
        if ($normalizedKey === 'content-type') {
            $metadata['content_type'] = $values[0];
        }
        // Extract Content-Length to content_length and first value
        elseif ($normalizedKey === 'content-length') {
            $metadata['content_length'] = $values[0];
        }
        // Extract x-blossom-<header name> to <header name> and first value
        elseif (strpos($normalizedKey, 'x-blossom-') === 0) {
            $headerName = substr($normalizedKey, 10);
            // Replace - with _ in header name
            $headerName = str_replace('-', '_', $headerName);
            $metadata[$headerName] = $values[0];
        }
    }
    return $metadata;
}