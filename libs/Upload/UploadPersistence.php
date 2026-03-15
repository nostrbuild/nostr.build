<?php

require_once __DIR__ . '/../utils.funcs.php';

class UploadPersistence
{
  private S3Service $s3Service;
  private UploadsData $uploadsData;
  private ?UsersImages $usersImages;
  private ?UsersImagesFolders $usersImagesFolders;
  private string $userNpub;
  private bool $pro;

  public function __construct(
    S3Service $s3Service,
    UploadsData $uploadsData,
    ?UsersImages $usersImages,
    ?UsersImagesFolders $usersImagesFolders,
    string $userNpub,
    bool $pro,
  ) {
    $this->s3Service = $s3Service;
    $this->uploadsData = $uploadsData;
    $this->usersImages = $usersImages;
    $this->usersImagesFolders = $usersImagesFolders;
    $this->userNpub = $userNpub;
    $this->pro = $pro;
  }

  /**
   * Store a free upload record in the database.
   *
   * Inserts into the uploads table without committing (deferred commit).
   *
   * @return int|false Insert ID on success, false on failure
   */
  public function storeFree(
    string $filename,
    string $metadata,
    int $fileSize,
    string $type = 'unknown',
    int $mediaWidth = 0,
    int $mediaHeight = 0,
    ?string $blurhash = null,
    ?string $mimeType = null,
    ?string $blossomHash = null,
  ): int|false {
    try {
      $insertId = $this->uploadsData->insert([
        'filename' => $filename,
        'metadata' => $metadata,
        'file_size' => $fileSize,
        'media_width' => $mediaWidth,
        'media_height' => $mediaHeight,
        'blurhash' => $blurhash,
        'usernpub' => $this->userNpub,
        'mime' => $mimeType,
        'type' => $type,
        'approval_status' => 'pending',
        'blossom_hash' => $blossomHash,
      ], false);
      return $insertId;
    } catch (\Throwable $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  /**
   * Store a pro upload record in the database.
   *
   * Inserts a placeholder row into users_images with a temporary filename
   * and the original SHA-256 hash. Does not commit (deferred commit).
   *
   * @param string $originalSha256 The SHA-256 hash of the original file
   * @return int|false Insert ID on success, false on failure
   */
  public function storePro(string $originalSha256): int|false
  {
    if ($this->usersImages === null) {
      error_log('storePro called but usersImages is not initialized');
      return false;
    }

    try {
      $tempFile = generateUniqueFilename('file_upload_');
      $insertId = $this->usersImages->insert([
        'usernpub' => $this->userNpub,
        'sha256_hash' => $originalSha256,
        'image' => $tempFile,
        'flag' => 0,
        'folder_id' => null,
      ], false);
      return $insertId;
    } catch (\Throwable $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  /**
   * Update a pro upload record with final file details after processing.
   *
   * Resolves the target folder from uppyMetadata or defaultFolderName,
   * creating the folder if necessary, then updates the row with the
   * final filename, dimensions, blurhash, MIME type, and other metadata.
   *
   * @return bool True on success, false on failure
   */
  public function updatePro(
    int $id,
    string $newName,
    int $fileSize,
    int $mediaWidth,
    int $mediaHeight,
    ?string $blurhash,
    string $fileMimeType,
    ?string $title = '',
    ?string $aiPrompt = '',
    ?string $blossomHash = null,
    array $uppyMetadata = [],
    string $defaultFolderName = '',
  ): bool {
    if ($this->usersImages === null) {
      error_log('updatePro called but usersImages is not initialized');
      return false;
    }

    // Resolve folder ID from uppy metadata or default folder name
    $folderId = null;
    $folderName = !empty($uppyMetadata['folderName'])
      ? json_decode($uppyMetadata['folderName']) ?? ''
      : null;
    $folderName = $folderName ?? $defaultFolderName;

    if (
      (!empty($uppyMetadata['folderHierarchy']) &&
        is_array($uppyMetadata['folderHierarchy']) &&
        count($uppyMetadata['folderHierarchy']) > 0) ||
      !empty($defaultFolderName)
    ) {
      if ($this->usersImagesFolders !== null) {
        try {
          $folderId = $this->usersImagesFolders->findFolderByNameOrCreate($this->userNpub, $folderName ?? '');
        } catch (\Throwable $e) {
          error_log($e->getMessage());
        }
      }
    }

    try {
      $this->usersImages->update($id, [
        'image' => $newName,
        'file_size' => $fileSize,
        'folder_id' => $folderId,
        'media_width' => $mediaWidth,
        'media_height' => $mediaHeight,
        'blurhash' => $blurhash,
        'mime_type' => $fileMimeType,
        'title' => $title,
        'ai_prompt' => $aiPrompt,
        'blossom_hash' => $blossomHash,
      ]);
    } catch (\Throwable $e) {
      error_log($e->getMessage());
      return false;
    }
    return true;
  }

  /**
   * Upload a file to S3.
   *
   * @param string $tmpPath Local path to the file to upload
   * @param string $objectName Target object name/key in S3
   * @param string $sha256 SHA-256 hash of the file contents
   * @return bool True on success, false on failure
   */
  public function uploadToS3(string $tmpPath, string $objectName, string $sha256 = ''): bool
  {
    try {
      $this->s3Service->uploadToS3($tmpPath, $objectName, $sha256, $this->userNpub, $this->pro);
    } catch (\Throwable $e) {
      error_log($e->getMessage());
      return false;
    }
    return true;
  }
}
