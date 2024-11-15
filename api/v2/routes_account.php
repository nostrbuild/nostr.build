<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrClient.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Create a new Permission object
$perm = new Permission();

$app->group('/account', function (RouteCollectorProxy $group) {
  // Route to retrieve files with optional folder id as an argument
  $group->get('/files[/{folder_id}]', function (Request $request, Response $response, array $args) {
    $folderId = $args['folder_id'] ?? null;
    $userImages = $this->get('userImages');
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImages->getFiles($_SESSION['usernpub'], $folderId);
      return jsonResponse($response, 'success', 'Files retrieved successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Files retrieval failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to upload file(s) via form
  $group->post('/files', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return jsonResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }
    $upload = $this->get('proUpload');

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
  });

  $group->post('/files/uppy', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // Get file(s) metadata
    $metadata = $request->getParsedBody();

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return uppyResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }

    $upload = $this->get('proUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles($files, $metadata);
      $upload->setUppyMetadata($metadata);

      [$status, $code, $message] = $upload->uploadFiles();

      if (!$status) {
        // Handle the non-true status scenario
        return uppyResponse($response, 'error', $message, new stdClass(), $code);
      }

      $data = $upload->getUploadedFiles();
      return uppyResponse($response, 'success', $message, $data, $code);
    } catch (\Exception $e) {
      return uppyResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to retrieve folders
  $group->get('/folders', function (Request $request, Response $response) {
    $userImagesFolders = $this->get('userImagesFolders');
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->getFolders($_SESSION['usernpub']);
      return jsonResponse($response, 'success', 'Folders retrieved successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folders retrieval failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to create a folder
  $group->post('/folders', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // If no folder name is provided, return a 400 response
    if (empty($data['folder_name'])) {
      return jsonResponse($response, 'error', 'No folder name provided', new stdClass(), 400);
    }
    $userImagesFolders = $this->get('userImagesFolders');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->insert([
        'usernpub' => $_SESSION['usernpub'],
        'folder' => $data['folder_name'],
      ]);
      return jsonResponse($response, 'success', 'Folder created successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folder creation failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to delete a folder
  $group->delete('/folders/{folder_id}', function (Request $request, Response $response, array $args) {
    $folderId = $args['folder_id'] ?? null;

    // If no folder id is provided, return a 400 response
    if (empty($folderId)) {
      return jsonResponse($response, 'error', 'No folder id provided', new stdClass(), 400);
    }
    $userImagesFolders = $this->get('userImagesFolders');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->delete([
        'id' => $folderId,
        'usernpub' => $_SESSION['usernpub'],
      ]);
      return jsonResponse($response, 'success', 'Folder deleted successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folder deletion failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to delete a file
  $group->delete('/files/{file_id}', function (Request $request, Response $response, array $args) {
    $fileId = $args['file_id'] ?? null;

    // If no file id is provided, return a 400 response
    if (empty($fileId)) {
      return jsonResponse($response, 'error', 'No file id provided', new stdClass(), 400);
    }
    $userImages = $this->get('userImages');

    // TODO: Implement deletion of files from S3 and DB in one transaction 
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImages->delete([
        'id' => $fileId,
        'usernpub' => $_SESSION['usernpub'],
      ]);
      return jsonResponse($response, 'success', 'File deleted successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'File deletion failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to upload a file via URL
  $group->post('/url', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // If no URL is provided, return a 400 response
    if (empty($data['url'])) {
      return jsonResponse($response, 'error', 'No URL provided', new stdClass(), 400);
    }
    $upload = $this->get('proUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class

      [$status, $code, $message] = $upload->uploadFileFromUrl($data['url']);

      if (!$status) {
        // Handle the non-true status scenario
        return jsonResponse($response, 'error', $message, new stdClass(), $code);
      }

      $result = $upload->getUploadedFiles();
      return jsonResponse($response, 'success', $message, $result, $code);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'URL processing failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to logout account
  $group->get('/logout', function (Request $request, Response $response) {
    // Unset all session variables
    $_SESSION = [];
    // Destroy the session
    session_destroy();
    // Return a 200 response
    return jsonResponse($response, 'success', 'Logged out', new stdClass(), 200);
  });

  // Route to login account using NostrAuth
  $group->post('/login', function (Request $request, Response $response) {
    $body = $request->getParsedBody();

    if (empty($body['npub'])) { // NIP-98 Flow
      // Get attributes from the middleware
      $npub = $request->getAttribute('npub');
      $accountExists = $request->getAttribute('account_exists') || false;
      $npubVerified = $request->getAttribute('npub_verified') || false;
      $npubLoginAllowed = $request->getAttribute('npub_login_allowed') || false;
      if (!empty($npub) && $accountExists && $npubLoginAllowed && $npubVerified) {
        // If the account exists, return a 200 response
        return jsonResponse($response, 'success', 'Account exists, and npub login enabled', new stdClass(), 200);
      } elseif (!empty($npub) && !$accountExists) {
        // Account does not exist, return a 404 response
        // Set signup npub in session to be used in the signup route
        $_SESSION['npub_verified'] = $npub;
        return jsonResponse($response, 'error', 'Account does not exist.', new stdClass(), 404);
      } elseif (!empty($npub) && $accountExists && !$npubLoginAllowed) {
        // Account exists but is not allowed to login, return a 403 response
        return jsonResponse($response, 'error', 'Account exists but npub login not enabled.', new stdClass(), 403);
      } else {
        // If the account does not exist, return a 401 response
        return jsonResponse($response, 'error', 'Not authorized', new stdClass(), 401);
      }
    } else {
      // DM Based login
      $npub = $body['npub'];
      try {
        $account = $this->get('accountClass')($npub);
      } catch (\Exception $e) {
        return jsonResponse($response, 'error', 'Login failed: ' . $e->getMessage(), new stdClass(), 500);
      }


      // Check if the user submitted a DM code
      if (!empty($body['dm_code'])) {
        // Process the DM code
        $dmCode = $body['dm_code'];
        // Verify that what we sent in DM matches what user submitted, and return a 200 response
        $timedDmCode = $_SESSION['timed_dm_code'];
        $expires = $timedDmCode['expires'];
        if (time() > $expires) {
          // If the DM code has expired, return a 401 response
          error_log('DM code expired');
          return jsonResponse($response, 'error', 'Login failed, code expired', new stdClass(), 401);
        }
        if ($timedDmCode['code'] === $dmCode) {
          // npub is verified, set session parameters
          $_SESSION['npub_verified'] = $npub;
          error_log('Npub verified: ' . $npub);
          try {
            // Reject login if account does not exist
            if (!$account->accountExists()) {
              return jsonResponse($response, 'error', 'Account does not exist.', new stdClass(), 404);
            }
            // Update DB to set npub_verified to true
            if (!$account->isNpubVerified()) {
              $account->verifyNpub();
            }
            if (!$account->isNpubLoginAllowed()) {
              // Check if user enabled npub login
              return jsonResponse($response, 'error', 'Login failed', new stdClass(), 403);
            }
            if (!$account->verifyNostrLogin()) {
              // This should never happen, but just in case
              return jsonResponse($response, 'error', 'Login failed', new stdClass(), 401);
            }
          } catch (\Exception $e) {
            return jsonResponse($response, 'error', 'Login failed: ' . $e->getMessage(), new stdClass(), 500);
          }
          return jsonResponse($response, 'success', 'Login successful.', new stdClass(), 200);
        } else {
          // If the DM code does not match, return a 401 response
          error_log('DM code does not match: ' . $dmCode . ' !== ' . $timedDmCode['code']);
          return jsonResponse($response, 'error', 'Login failed, code does not match.', new stdClass(), 401);
        }
      } else {
        // If the user did not submit a DM code, send a DM to the user
        // Generate random secure string and send it in a DM to the user
        $dmCode = base64_encode(random_bytes(16)); // 16 bytes is sufficient for this use case
        $dmCodeUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/login?n=' . urlencode($npub) . '&c=' . urlencode($dmCode);
        $_SESSION['timed_dm_code']['code'] = $dmCode;
        $_SESSION['timed_dm_code']['expires'] = time() + 300 + 10; // 5 minutes + 10 seconds
        try {
          $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
          if (!$nc->sendDm($npub, ['Your temporary login code and URL is:', $dmCode, $dmCodeUrl], true)) {
            throw new Exception('Error sending DM');
          }
        } catch (\Exception $e) {
          error_log('Error sending DM: ' . $e->getMessage());
          return jsonResponse($response, 'error', 'Error sending DM', new stdClass(), 500);
        }
        // Return a 200 response
        return jsonResponse($response, 'success', 'DM sent', new stdClass(), 200);
      }
    }
  })->add(new NostrLoginMiddleware());

  // Route to verify npub ownership
  $group->post('/verify', function (Request $request, Response $response) {
    // Check if request has body and containes npub field in it
    $body = $request->getParsedBody();
    if (!empty($body['npub'])) {
      // DM Based verification
      $npub = $body['npub'];
      // Check if the user submitted a DM code
      if (!empty($body['dm_code'])) {
        // Process the DM code
        $dmCode = (int)$body['dm_code'];
        // Verify that what we sent in DM matches what user submitted, and return a 200 response
        $timedDmCode = $_SESSION['timed_dm_code'];
        $expires = $timedDmCode['expires'];
        if (time() > $expires) {
          // If the DM code has expired, return a 401 response
          error_log('DM code expired');
          return jsonResponse($response, 'error', 'Verification failed, code expired', new stdClass(), 401);
        }
        if ($timedDmCode['code'] === $dmCode) {
          try {
            // Update DB to set npub_verified to true
            $accountFactory = $this->get('accountClass');
            $account = $accountFactory($npub);
            if ($account->accountExists() && !$account->isNpubVerified()) {
              $account->verifyNpub();
            }
            $account->updateAccountDataFromNostrApi(false, $account->accountExists());
            $account->setSessionParameters();
          } catch (\Exception $e) {
            error_log('Error updating DB for npub verification: ' . $e->getMessage());
          }
          $_SESSION['signup_npub_verified'] = $npub;
          $_SESSION['npub_verified'] = $npub;
          error_log('Npub verified: ' . $npub);
          return jsonResponse($response, 'success', 'Account exists', new stdClass(), 200);
        } else {
          // If the DM code does not match, return a 401 response
          error_log('DM code does not match: ' . $dmCode . ' !== ' . $timedDmCode['code']);
          return jsonResponse($response, 'error', 'Verification failed', new stdClass(), 401);
        }
      } else {
        // If the user did not submit a DM code, send a DM to the user
        // Generate random 6 digit code
        $dmCode = rand(100000, 999999);
        $_SESSION['timed_dm_code']['code'] = $dmCode;
        $_SESSION['timed_dm_code']['expires'] = time() + 660 + 10; // 11 minutes + 10 seconds
        error_log('DM code: ' . $dmCode);
        // Send a DM to the user
        try {
          $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
          if (!$nc->sendDm($npub, ['Your verification code is:', (string)$dmCode], true)) {
            throw new Exception('Error sending DM');
          }
        } catch (\Exception $e) {
          error_log('Error sending DM: ' . $e->getMessage());
          return jsonResponse($response, 'error', 'Error sending DM', new stdClass(), 500);
        }
        // Return a 200 response
        return jsonResponse($response, 'success', 'DM sent', new stdClass(), 200);
      }
    } else {
      // NIP-98 Based verification
      // Get attributes from the middleware
      $npub = $request->getAttribute('npub');
      $accountExists = $request->getAttribute('account_exists') || false;
      $accountVerified = $request->getAttribute('npub_verified') || false;
      $accountVerified = $request->getAttribute('npub_login_allowed') || false;
      if (!empty($npub) && (!$accountExists || !$accountVerified)) {
        // Return a 200 response when npub is verified but account does not exist
        $_SESSION['signup_npub_verified'] = $npub;
        $_SESSION['npub_verified'] = $npub;
        return jsonResponse($response, 'success', 'Account exists', new stdClass(), 200);
      } elseif (!empty($npub) && $accountExists && $accountVerified) {
        // Return a 409 response when npub is verified and account exists
        return jsonResponse($response, 'error', 'Account exists and is already verified', new stdClass(), 409);
      } else {
        // If the account does not exist, return a 404 response
        return jsonResponse($response, 'error', 'Verification failed', new stdClass(), 401);
      }
    }
  })->add(new NostrLoginMiddleware());

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  })->add(function ($request, $handler) {
    // Additional middleware logic for this route
    // Continue to the route if everything checks out
    return $handler->handle($request);
  });
})->add(function ($request, $handler) use ($perm) {
  // Check if the request is coming to /login and bypass the authentication
  if (
    $request->getUri()->getPath() === '/api/v2/account/login' ||
    $request->getUri()->getPath() === '/api/v2/account/verify'
  ) {
    return $handler->handle($request);
  }
  // Check if the user is logged in
  // This would be the place to allow other authentication methods like JWT, NIP-98, etc.
  if (!$perm->validateLoggedin() || $perm->validatePermissionsLevelEqual(4)) {
    error_log('User not authenticated or authorized');
    $response = new Slim\Psr7\Response(); // Create a new response object
    return jsonResponse($response, 'error', 'User not authenticated or authorized', new stdClass(), 401);
  }
  // Check if the user account has expired
  $account = $this->get('accountClass')($_SESSION['usernpub']);
  if ($account->isExpired()) {
    error_log('User account expired: ' . $_SESSION['usernpub']);
    $response = new Slim\Psr7\Response(); // Create a new response object
    return jsonResponse($response, 'error', 'User account expired', new stdClass(), 401);
  }
  error_log('User authenticated and authorized: ' . $_SESSION['usernpub'] . PHP_EOL);
  // If the user is logged in and has the necessary permissions, continue to the route
  return $handler->handle($request);
});
