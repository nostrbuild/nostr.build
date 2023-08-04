<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/NostrAuthHandler.class.php';
require_once __DIR__ . '/Account.class.php';

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Summary of NostrAuthMiddleware
 */
class NostrAuthMiddleware implements MiddlewareInterface
{
  /**
   * Summary of process
   * @param Psr\Http\Message\ServerRequestInterface $request
   * @param Psr\Http\Server\RequestHandlerInterface $handler
   * @return Psr\Http\Message\ResponseInterface
   * 
   * With this middleware we want to achieve the following:
   * 1. Check if the request has a valid NIP-98 Authorization header
   * 2. If it does, get the npub from the header and add it to the request attributes
   * 3. If it does not, do nothing
   * 
   * The npub will be used to get the account information from the database
   * Then we will validate if user exists, is at the right level, has sufficient storage to upload the file, etc.
   * If any of the checks indicate that user cannot store the upload in their account, we default to unauthenticated free upload
   */

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    global $link;
    $headers = $request->getHeaders();

    $npub = null; // Initialize as null
    try {
      // Initialize NostrAuthHandler
      $authHandler = new NostrAuthHandler($headers, $request);

      $authHandler->handle();
      $npub = $authHandler->getNpub();

      // Initialize Account class
      $account = new Account($npub, $link);

      if ($account->isAccountValid()) {
        // Calculate projected upload size and check if user has enough storage
        $uploadedFiles = $request->getUploadedFiles();
        $totalSize = 0;

        // Iterate over each uploaded file
        foreach ($uploadedFiles as $uploadedFile) {
          // Add the size of each uploaded file to the total size
          if ($uploadedFile instanceof UploadedFileInterface) {
            $totalSize += $uploadedFile->getSize();
          }
        }

        if (!$account->hasSufficientStorageSpace($totalSize)) {
          error_log('User ' . $npub . ' does not have sufficient storage space to upload the file');
          $npub = null;
        }
      } else {
        $npub = null;
      }
    } catch (\Exception $e) {
      error_log('NostrAuthHandler error: ' . $e->getMessage());
    }

    // Lastly, add the npub to the request attributes
    $request = $request->withAttribute('npub', $npub);
    $response = $handler->handle($request);

    return $response;
  }
}
