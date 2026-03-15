<?php

require_once __DIR__ . '/../../SiteConfig.php';
require_once __DIR__ . '/../utils.funcs.php';

class UploadValidator
{
  const IMAGE_SIZE_MULTIPLIER = 2;

  private UploadsData $uploadsData;

  public function __construct(UploadsData $uploadsData)
  {
    $this->uploadsData = $uploadsData;
  }

  /**
   * Validate a file before upload processing.
   *
   * @param array $file File data array with keys: error, tmp_name, size, sha256
   * @param bool $pro Whether the user has a pro account
   * @param bool $noTransform Whether transformation is disabled
   * @param Account|null $account The user's account object (required for pro users)
   * @param string $userNpub The user's npub for blacklist checking
   * @return array{0: bool, 1: int, 2: string} [success, HTTP status code, message]
   */
  public function validate(array $file, bool $pro, bool $noTransform, ?Account $account, string $userNpub = ''): array
  {
    if (
      !is_array($file) ||
      !isset($file['error'], $file['tmp_name'], $file['size'], $file['sha256']) ||
      !is_string($file['tmp_name']) ||
      $file['tmp_name'] === '' ||
      !is_file($file['tmp_name'])
    ) {
      error_log('Invalid file structure for upload validation');
      return [false, 400, 'Invalid file upload payload'];
    }

    // Validate if file upload is OK
    if ($file['error'] !== UPLOAD_ERR_OK) {
      error_log('File upload error: ' . $file['error']);
      return [false, 400, "File upload error "];
    }

    $userAccountLevel = 0;
    try {
      $userAccountLevel = $pro && $account !== null ? $account->getAccountLevelInt() : 0;
    } catch (Exception $e) {
      error_log('Failed to get account level: ' . $e->getMessage());
    }

    $fileType = detectFileExt($file['tmp_name'], $userAccountLevel);
    $multiplierSize = 1;
    // Check if uploaded file is an image, and add a fuzz factor to account for future optimization
    if ($fileType['type'] === 'image' && !$noTransform) {
      $multiplierSize = self::IMAGE_SIZE_MULTIPLIER;
    }

    // Check if the file size exceeds the upload limit for free users
    if (!$pro && $file['size'] > (SiteConfig::FREE_UPLOAD_LIMIT * $multiplierSize)) {
      error_log('File size exceeds the limit of ' . formatSizeUnits(SiteConfig::FREE_UPLOAD_LIMIT));
      return [false, 413, "File size exceeds the limit of " . formatSizeUnits(SiteConfig::FREE_UPLOAD_LIMIT)];
    }

    // Check if file has been rejected for free users or if the user has been flagged as rejected
    if (
      (!$pro && $this->uploadsData->checkRejected($file['sha256'])) ||
      (!empty($userNpub) && $this->uploadsData->checkBlacklisted($userNpub))
    ) {
      error_log('File has been flagged as rejected');
      return [false, 403, "File or User has been flagged as rejected"];
    }

    // Calculate remaining space and check if file size exceeds the remaining space for pro users
    if ($pro && $account !== null) {
      // Check if account has expired
      if ($account->isExpired()) {
        error_log('Account has expired');
        return [false, 403, "Account has expired, please renew at https://nostr.build/plans/"];
      }
      // TODO: Need to validate array of files, so we do not allow to go over the limit with batch
      if (!$account->hasSufficientStorageSpace($file['size'])) {
        error_log('File size exceeds the remaining space of ' . formatSizeUnits($account->getPerFileUploadLimit()));
        return [false, 413, "File size exceeds the remaining space of " . formatSizeUnits($account->getPerFileUploadLimit())];
      }
    }

    return [true, 200, "Validation successful"];
  }
}
