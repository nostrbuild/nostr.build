<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrAuthMiddleware.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Route to upload a file via from or URL
 * 
 * Returns JSON data with the following structure:
 *   {
 *    'status' => <'success' or 'error'>,
 *    'message' => <message about the status of the request>,
 *    'data' => {
 *    { /* data about the file, or empty object in case of error * /
 *      'fileName' => <name of the file with extention>,
 *      'url' => <url of the file>,
 *      'thumbnail' => <url of the thumbnail of the file>,
 *      'blurhash' => <blurhash of the file>,
 *      'sha256' => <sha256 of the file>,
 *      'type' => <'image', 'video', 'audio', 'profile' or 'other'>,
 *      'mime' => <mime type of the file>,
 *      'size' => <size of the file in bytes>,
 *      'metadata' => <metadata of the file>,
 *      'dimentions' => {
 *        'width' => <width of the file in pixels>,
 *        'height' => <height of the file in pixels>,
 *      },
 *      'responsive' => {
 *        '240p' => <url of the 428x426 responsive image>,
 *        '360p' => <url of the 640x640 responsive image>,
 *        '480p' => <url of the 854x854 responsive image>,
 *        '720p' => <url of the 1280x1280 responsive image>,
 *        '1080p' => <url of the 1920x1920 responsive image>,
 *     },
 *    },
 *    ...
 *  }
 * }
 */

/*
CURL command to test the upload API with multiple files:
curl --location --request POST 'https://nostr.build/api/v2/upload/files' \
--header 'Content-Type: multipart/form-data' \
--form 'file[]=@"/path/to/image1.png"' \
--form 'file[]=@"/path/to/image2.png"' \
--form 'file[]=@"/path/to/image3.png"'

Actual full output of the upload API with multiple files:
{
  "status": "success",
  "message": "Files uploaded successfully",
  "data": [
    {
      "input_name": "APIv2",
      "name": "2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
      "url": "https://nostr.build/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
      "thumbnail": "https://nostr.build/thumbnail/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
      "responsive": {
        "240p": "https://nostr.build/responsive/240p/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
        "360p": "https://nostr.build/responsive/360p/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
        "480p": "https://nostr.build/responsive/480p/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
        "720p": "https://nostr.build/responsive/720p/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png",
        "1080p": "https://nostr.build/responsive/1080p/i/2489ee648a4fef6943f4a7c88349477e78a91e28232246b801fe8ce86e64624e.png"
      },
      "blurhash": "LBDIUov100y??v9tIU-p0{GEr?v#",
      "sha256": "17c3bef58e3c3608615f81a1e5b58174c20ce0338837cd7f66f2b44f852ea8c2",
      "type": "picture",
      "mime": "image/png",
      "size": 21988,
      "metadata": {
        "date:create": "2023-06-27T21:36:48+00:00",
        "date:modify": "2023-06-27T21:36:48+00:00",
        "png:IHDR.bit-depth-orig": "8",
        "png:IHDR.bit_depth": "8",
        "png:IHDR.color-type-orig": "3",
        "png:IHDR.color_type": "3 (Indexed)",
        "png:IHDR.interlace_method": "0 (Not interlaced)",
        "png:IHDR.width,height": "432, 432",
        "png:PLTE.number_colors": "94",
        "png:sRGB": "intent=0 (Perceptual Intent)",
        "png:tRNS": "chunk was found"
      },
      "dimensions": {
        "width": 432,
        "height": 432
      }
    },
    {
      "input_name": "APIv2",
      "name": "4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
      "url": "https://nostr.build/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
      "thumbnail": "https://nostr.build/thumbnail/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
      "responsive": {
        "240p": "https://nostr.build/responsive/240p/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
        "360p": "https://nostr.build/responsive/360p/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
        "480p": "https://nostr.build/responsive/480p/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
        "720p": "https://nostr.build/responsive/720p/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png",
        "1080p": "https://nostr.build/responsive/1080p/i/4b5c01baeb8381a4de353886c5adfbeb61a16b3fef8e06b45e4b64e5c1bf1ab5.png"
      },
      "blurhash": "LBDR]Ov200y?.S9tIU-p0{GEr?v#",
      "sha256": "4b123a35e8979f88ec84dbc3cafbcbf7a817848a928d4c1484dc144aadc6f51f",
      "type": "picture",
      "mime": "image/png",
      "size": 13949,
      "metadata": {
        "date:create": "2023-06-27T21:36:49+00:00",
        "date:modify": "2023-06-27T21:36:49+00:00",
        "png:IHDR.bit-depth-orig": "8",
        "png:IHDR.bit_depth": "8",
        "png:IHDR.color-type-orig": "3",
        "png:IHDR.color_type": "3 (Indexed)",
        "png:IHDR.interlace_method": "0 (Not interlaced)",
        "png:IHDR.width,height": "288, 288",
        "png:PLTE.number_colors": "120",
        "png:sRGB": "intent=0 (Perceptual Intent)",
        "png:tRNS": "chunk was found"
      },
      "dimensions": {
        "width": 288,
        "height": 288
      }
    },
    {
      "input_name": "APIv2",
      "name": "30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
      "url": "https://nostr.build/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
      "thumbnail": "https://nostr.build/thumbnail/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
      "responsive": {
        "240p": "https://nostr.build/responsive/240p/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
        "360p": "https://nostr.build/responsive/360p/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
        "480p": "https://nostr.build/responsive/480p/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
        "720p": "https://nostr.build/responsive/720p/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png",
        "1080p": "https://nostr.build/responsive/1080p/i/30e9887f155c0f083affa60660ece63b0848f27a45ab4004a438d98cf2b40497.png"
      },
      "blurhash": "LBDIUou*00y?.S9tMx-p0{GEr?v#",
      "sha256": "b02328535060e12014abda70df62a9c5d2ec7ef28e6a673cff2360046a765ec0",
      "type": "picture",
      "mime": "image/png",
      "size": 9630,
      "metadata": {
        "date:create": "2023-06-27T21:36:49+00:00",
        "date:modify": "2023-06-27T21:36:49+00:00",
        "png:IHDR.bit-depth-orig": "8",
        "png:IHDR.bit_depth": "8",
        "png:IHDR.color-type-orig": "3",
        "png:IHDR.color_type": "3 (Indexed)",
        "png:IHDR.interlace_method": "0 (Not interlaced)",
        "png:IHDR.width,height": "216, 216",
        "png:PLTE.number_colors": "134",
        "png:sRGB": "intent=0 (Perceptual Intent)",
        "png:tRNS": "chunk was found"
      },
      "dimensions": {
        "width": 216,
        "height": 216
      }
    }
  ]
}

PFP Upload Returned value:
  Example returned value in JSON format:
  {
    "status": "success",
    "message": "Profile picture uploaded successfully",
    "data": [
      {
        "input_name": "APIv2",
        "name": "83d5d416ed0ee14ac03eea5e9ee530de6fded94632143aee026677718a331c53.png",
        "url": "https://test.nostr.build/i/p/83d5d416ed0ee14ac03eea5e9ee530de6fded94632143aee026677718a331c53.png",
        "sha256": "83d5d416ed0ee14ac03eea5e9ee530de6fded94632143aee026677718a331c53",
        "type": "profile",
        "mime": "image/png",
        "size": 20465
      }
    ]
  }

*/

$app->group('/upload', function (RouteCollectorProxy $group) {
  // Route to upload file(s) via form
  $group->post('/files', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // Log request route
    error_log('Route: /upload/files');

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return jsonResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }
    // NIP-98 handling
    $npub = $request->getAttribute('npub');
    $accountUploadEligible = $request->getAttribute('account_upload_eligible');
    $accountDefaultFolder = $request->getAttribute('account_default_folder');
    $factory = $this->get('multimediaUploadFactory');

    if (null !== $npub) {
      error_log('npub: ' . $npub . ' uploading files');
      $upload = $factory->create($accountUploadEligible, $npub);
      if (!empty($accountDefaultFolder)) {
        $upload->setDefaultFolderName($accountDefaultFolder);
      }
    } else {
      error_log('Unauthenticated upload of files');
      $upload = $factory->create();
    }
    error_log(PHP_EOL . "Request URL:" . $request->getUri() . PHP_EOL);

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles($files);

      [$status, $code, $message] = $upload->uploadFiles();

      if (!$status) {
        // Handle the non-true status scenario
        return jsonResponse($response, 'error', $message, new stdClass(), $code);
      }

      $data = $upload->getUploadedFiles();
      return jsonResponse($response, 'success', $message, $data, $code);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  })->add(new NostrAuthMiddleware());

  // Route to upload a profile picture

  $group->post('/profile', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // If no files or more than one file are provided, return a 400 response
    if (empty($files) || count($files) > 1) {
      return jsonResponse($response, 'error', 'Either no file or more than one file provided. Only one file is expected.', new stdClass(), 400);
    }
    $npub = $request->getAttribute('npub');
    $factory = $this->get('multimediaUploadFactory');

    if (null !== $npub) {
      error_log('npub: ' . $npub . ' uploading pfp');
      $upload = $factory->create(false, $npub);
    } else {
      error_log('Unauthenticated upload of pfp');
      $upload = $factory->create();
    }

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles([reset($files)]);

      [$status, $code, $message] = $upload->uploadProfilePicture();

      if (!$status) {
        // Handle the non-true status scenario
        return jsonResponse($response, 'error', $message, new stdClass(), $code);
      }

      $data = $upload->getUploadedFiles();
      return jsonResponse($response, 'success', $message, $data, $code);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  })->add(new NostrAuthMiddleware());

  // Route to upload a file via URL
  $group->post('/url', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // If no URL is provided, return a 400 response
    if (empty($data['url'])) {
      return jsonResponse($response, 'error', 'No URL provided', new stdClass(), 400);
    }
    // NIP-98 handling
    $npub = $request->getAttribute('npub');
    $accountUploadEligible = $request->getAttribute('account_upload_eligible');
    $accountDefaultFolder = $request->getAttribute('account_default_folder');
    $factory = $this->get('multimediaUploadFactory');

    if (null !== $npub) {
      error_log('npub: ' . $npub . ' uploading from URL: ' . $data['url']);
      $upload = $factory->create($accountUploadEligible, $npub);
      if (!empty($accountDefaultFolder)) {
        $upload->setDefaultFolderName($accountDefaultFolder);
      }
    } else {
      error_log('Unauthenticated upload from URL: ' . $data['url']);
      $upload = $factory->create();
    }

    try {
      // Handle exceptions thrown by the MultimediaUpload class

      [$status, $code, $message] = $upload->uploadFileFromUrl($data['url']);

      if (!$status) {
        // Handle the non-true status scenario
        return jsonResponse($response, 'error', $message, new stdClass(), $code);
      }

      $data = $upload->getUploadedFiles();
      return jsonResponse($response, 'success', $message, $data, $code);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'URL processing failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  })->add(new NostrAuthMiddleware());

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  });
});
