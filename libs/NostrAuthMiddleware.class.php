<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/NostrAuthHandler.class.php';
require_once __DIR__ . '/Account.class.php';
require_once __DIR__ . '/NostrClient.class.php';

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
    $accountUploadEligible = true;  // Assume the account is eligible by default

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
          $accountUploadEligible = false;
        } else {
          error_log('User ' . $npub . ' has sufficient storage space to upload the file:' . $account->getRemainingStorageSpace() . ' bytes');
        }

        // Validate account expiration
        if ($account->isExpired()) {
          error_log('User ' . $npub . ' account has expired');
          $accountUploadEligible = false;
          // Notify npub via DM that their account has expired
          // Check if we already sent a notification in the last 7 days
          // Do not send more than one per week, and stop sending after 10 weeks from expiration
          $daysSinceExpiration = $account->getDaysPastSubscriptionExpiration();
          $notify = (
            $account->getDaysPastLastNotification() > 7 || // More than 7 days since last notification
            $account->getDaysPastLastNotification() === -1) && // No notification sent yet
            $daysSinceExpiration < 70; // Less than 10 weeks past expiration
          // LOG all vars for DEBUG
          error_log('Days since expiration: ' . $daysSinceExpiration);
          error_log('Days past last notification: ' . $account->getDaysPastLastNotification());
          error_log('Notify: ' . (string)$notify);
          if ($notify) { // Disable notifications for now
            try {
              $dmMsg = 'Your account has expired ' . (string)$daysSinceExpiration . ' days ago. Please renew your subscription at https://nostr.build/account/';
              $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
              if (!$nc->sendDm($npub, [$dmMsg])) {
                throw new Exception('Error sending DM');
              } else {
                // Update last notification date
                $account->updateLastNotificationDate();
              }
            } catch (\Exception $e) {
              error_log('Error sending DM: ' . $e->getMessage());
            }
          }
        } elseif ($account->getDaysUntilSubscriptionExpiration() <= 30) {
          // Notify npub via DM that their account is about to expire
          // Check if we already sent a notification in the last 7 days
          // Do not send more than one per week, and stop sending after 10 weeks from expiration
          $daysUntilExpiration = $account->getDaysUntilSubscriptionExpiration();
          $notify =
            $account->getDaysPastLastNotification() > 7 || // More than 7 days since last notification
            $account->getDaysPastLastNotification() === -1; // No notification sent yet
          // LOG all vars for DEBUG
          error_log('Days until expiration: ' . $daysUntilExpiration);
          error_log('Days past last notification: ' . $account->getDaysPastLastNotification());
          error_log('Notify: ' . (string)$notify);
          if ($notify) { // Disable notifications for now
            try {
              $dmMsg = 'Your account will expire in ' . (string)$daysUntilExpiration . ' days. Please renew your subscription at https://nostr.build/account/';
              $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
              if (!$nc->sendDm($npub, [$dmMsg])) {
                throw new Exception('Error sending DM');
              } else {
                // Update last notification date
                $account->updateLastNotificationDate();
              }
            } catch (\Exception $e) {
              error_log('Error sending DM: ' . $e->getMessage());
            }
          }
        }
      } else {
        error_log('User ' . $npub . ' account is not valid');
        $accountUploadEligible = false;
      }
    } catch (\Exception $e) {
      error_log('NostrAuthHandler error: ' . $e->getMessage());
      $accountUploadEligible = false;
    }

    // Lastly, add the npub to the request attributes
    $request = $request->withAttribute('npub', $npub);
    $request = $request->withAttribute('account_upload_eligible', $accountUploadEligible);
    $response = $handler->handle($request);

    return $response;
  }
}

/**
 * Summary of FormAuthorizationMiddleware
 * Handles the case where the Authorization header is not set, but the form data contains the Authorization field
 */
class FormAuthorizationMiddleware implements MiddlewareInterface
{
  /**
   * Summary of process
   * @param Psr\Http\Message\ServerRequestInterface $request
   * @param Psr\Http\Server\RequestHandlerInterface $handler
   * @return Psr\Http\Message\ResponseInterface
   */
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    // If the 'Authorization' header is not set
    if (!$request->hasHeader('Authorization')) {
      // Get parsed body
      $body = $request->getParsedBody();

      // Check if the 'Authorization' field is set in the form data
      if (isset($body['Authorization']) && !empty($body['Authorization'])) {
        // Add it to the request headers
        $request = $request->withHeader('Authorization', $body['Authorization']);
      }
    }

    // Call the next middleware or route
    return $handler->handle($request);
  }
}

class NostrLoginMiddleware implements MiddlewareInterface
{
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    global $link;
    $headers = $request->getHeaders();

    $npub = null; // Initialize as null
    $accountExists = false;  // Assume the account does not exist by default
    $npubLoginAllowed = false; // Assume the account is not allowed to login by default
    $npubVerified = false; // Assume the account is not verified by default
    try {
      // Initialize NostrAuthHandler
      $authHandler = new NostrAuthHandler($headers, $request);

      $authHandler->handle();
      $npub = $authHandler->getNpub();

      // Initialize Account class
      $account = new Account($npub, $link);

      // Verify account if it exists and is valid
      if ($account->accountExists() && $account->isAccountValid() && !$account->isNpubVerified()) {
        $account->verifyNpub();
      }

      if (
        $account->accountExists() &&
        $account->isAccountValid() &&
        $account->isNpubLoginAllowed()
      ) {
        error_log('Account exists, is valid and is allowed to login');
        $accountExists = true;
        $npubLoginAllowed = true;
        $npubVerified = true;
        $account->verifyNostrLogin();
      } elseif (
        $account->accountExists() &&
        $account->isAccountValid() &&
        !$account->isNpubLoginAllowed()
      ) {
        error_log('Account exists, is valid but is not allowed to login');
        $accountExists = true;
        $npubVerified = true;
      } else {
        error_log('Account does not exist or is not valid');
        $npubVerified = true;
        $account->updateAccountDataFromNostrApi(true, false); // Update account data from Nostr API, but do not touch DB
        $account->setSessionParameters();
      }
    } catch (\Exception $e) {
      error_log('NostrAuthHandler error: ' . $e->getMessage());
      $accountExists = false;
    }

    // Lastly, add the npub to the request attributes
    $request = $request->withAttribute('npub', $npub)
      ->withAttribute('account_exists', $accountExists)
      ->withAttribute('npub_verified', $npubVerified)
      ->withAttribute('npub_login_allowed', $npubLoginAllowed);
    $response = $handler->handle($request);

    return $response;
  }
}
