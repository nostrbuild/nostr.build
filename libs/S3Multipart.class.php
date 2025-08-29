<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/CloudflareUploadWebhook.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * S3Multipart class for handling AWS S3 multipart uploads
 * 
 * This class handles the presigning of S3 multipart upload operations:
 * - Creating multipart uploads
 * - Signing individual parts
 * - Completing multipart uploads
 * - Aborting multipart uploads
 * 
 * Usage:
 * $s3Multipart = new S3Multipart($awsConfig, $db);
 * $result = $s3Multipart->createMultipartUpload($filename, $contentType, $metadata, $userNpub);
 */
class S3Multipart
{
  private $s3Client;
  private $bucket;
  private $db;
  private $usersImages;
  private $usersImagesFolders;
  private $s3Service;
  private $userNpub;
  private $uploadWebhook;

  /**
   * Constructor
   * 
   * @param array $awsConfig AWS configuration array
   * @param mysqli $db Database connection
   * @param string $userNpub User's npub
   */
  public function __construct($awsConfig, $db)
  {
    // Initialize S3 client for the upload bucket (separate from main storage)
    if (
      !isset($awsConfig['upload']['credentials']['key']) ||
      !isset($awsConfig['upload']['credentials']['secret']) ||
      !isset($awsConfig['upload']['region']) ||
      !isset($awsConfig['upload']['bucket'])
    ) {
      throw new Exception("S3 upload credentials are not set in the config file.");
    }

    $this->s3Client = new S3Client($awsConfig['upload']);
    $this->bucket = $awsConfig['upload']['bucket'];
    $this->db = $db;
    $this->usersImages = new UsersImages($db);
    $this->usersImagesFolders = new UsersImagesFolders($db);
    $this->s3Service = new S3Service($awsConfig);
    $this->userNpub = $_SESSION['usernpub'];
    // Upload hook class
    $this->uploadWebhook = new CloudflareUploadWebhook(
      $_SERVER['NB_API_UPLOAD_SECRET'],
      $_SERVER['NB_API_UPLOAD_INFO_URL'],
    );
  }

  /**
   * Create a multipart upload
   * 
   * @param string $filename Original filename
   * @param string $contentType MIME type
   * @param array $metadata Additional metadata
   * @param string $userNpub User's npub
   * @return array|false Upload ID and key or false on failure
   */
  public function createMultipartUpload(string $filename, string $contentType, array $metadata, string $userNpub)
  {
    try {
      // Validate user account
      $account = new Account($userNpub, $this->db);
      if ($account->isExpired()) {
        throw new Exception('Account has expired');
      }

      // Generate unique key for the upload
      $key = $this->generateUploadKey($filename, $userNpub, $contentType);

      // Create multipart upload
      $result = $this->s3Client->createMultipartUpload([
        'Bucket' => $this->bucket,
        'Key' => $key,
        'ContentType' => $contentType,
        'Metadata' => [
          'original-filename' => $filename,
          'user-npub' => $userNpub,
          'upload-time' => (string)time(),
          'metadata' => json_encode($metadata)
        ]
      ]);

      $uploadId = $result['UploadId'];

      $uploadIdShort = substr($uploadId, 0, 10);
      $userNpubShort = substr($userNpub, 0, 10);
      $keyShort = substr($key, 0, 10);
      error_log("Created multipart upload: $uploadIdShort for user: $userNpubShort, key: $keyShort");

      return [
        'uploadId' => $uploadId,
        'key' => $key
      ];
    } catch (AwsException $e) {
      error_log('AWS Error creating multipart upload: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error creating multipart upload: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Sign a part for upload
   * 
   * @param string $uploadId Upload ID
   * @param string $key Object key
   * @param int $partNumber Part number (1-based)
   * @param string $userNpub User's npub
   * @return array|false Presigned URL and headers or false on failure
   */
  public function signPart(string $uploadId, string $key, int $partNumber, string $userNpub)
  {
    try {
      // Validate part number
      if ($partNumber < 1 || $partNumber > 10000) {
        throw new Exception('Invalid part number. Must be between 1 and 10000.');
      }

      // Validate user owns this upload
      if (!$this->validateUserOwnership($uploadId, $userNpub, $key)) {
        throw new Exception('User does not own this upload');
      }

      // Create presigned URL for uploading the part
      $cmd = $this->s3Client->getCommand('UploadPart', [
        'Bucket' => $this->bucket,
        'Key' => $key,
        'UploadId' => $uploadId,
        'PartNumber' => $partNumber
      ]);

      $request = $this->s3Client->createPresignedRequest($cmd, '+15 minutes');

      $uploadIdShort = substr($uploadId, 0, 10);
      $userNpubShort = substr($userNpub, 0, 10);
      error_log("Signed part $partNumber for upload: $uploadIdShort, user: $userNpubShort");

      return [
        'url' => (string)$request->getUri(),
        'headers' => [
          'Content-Type' => 'application/octet-stream'
        ]
      ];
    } catch (AwsException $e) {
      error_log('AWS Error signing part: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error signing part: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Complete multipart upload
   * 
   * @param string $uploadId Upload ID
   * @param string $key Object key
   * @param array $parts Array of parts with PartNumber and ETag
   * @param string $userNpub User's npub
   * @return array|false Success data or false on failure
   */
  public function completeMultipartUpload(string $uploadId, string $key, array $parts, string $userNpub)
  {
    try {
      // Validate user owns this upload
      if (!$this->validateUserOwnership($uploadId, $userNpub, $key)) {
        throw new Exception('User does not own this upload');
      }

      // Check if object already exists as a completed upload (disconnect scenario)
      $s3ObjectExists = $this->checkS3ObjectExists($key);
      
      if ($s3ObjectExists) {
        // Object already exists - skip S3 completion, just do copy and DB work
        $uploadIdShort = substr($uploadId, 0, 10);
        error_log("S3 object already exists for upload: $uploadIdShort - skipping S3 completion, doing copy+DB only");
        
        // Get upload metadata from existing S3 object
        $uploadInfo = $this->getUploadInfoFromS3($uploadId, $key);
        
        if (!$uploadInfo) {
          error_log("Failed to get upload info from existing S3 object for upload: " . substr($uploadId, 0, 10));
          return [
            'error' => 'Failed to retrieve upload information from existing object'
          ];
        }
        
      } else {
        // Normal flow - complete the multipart upload first
        
        // Format parts for AWS
        $awsParts = [];
        foreach ($parts as $part) {
          $awsParts[] = [
            'ETag' => $part['ETag'],
            'PartNumber' => (int)$part['PartNumber']
          ];
        }

        // Sort parts by part number
        usort($awsParts, function ($a, $b) {
          return $a['PartNumber'] - $b['PartNumber'];
        });

        // Complete the multipart upload
        $result = $this->s3Client->completeMultipartUpload([
          'Bucket' => $this->bucket,
          'Key' => $key,
          'UploadId' => $uploadId,
          'MultipartUpload' => [
            'Parts' => $awsParts
          ]
        ]);

        // Get upload metadata from S3 after completion
        $uploadInfo = $this->getUploadInfoFromS3($uploadId, $key);
      }

      if (!$uploadInfo) {
        error_log("Failed to get upload info from S3 for upload: " . substr($uploadId, 0, 10));
        return [
          'error' => 'Failed to retrieve upload information from storage'
        ];
      }

      // Copy file to final storage bucket using existing S3Service
      $copyResult = $this->copyToFinalStorage($key, $uploadInfo);

      if (!$copyResult) {
        error_log("Failed to copy file to final storage for upload: " . substr($uploadId, 0, 10));
        return [
          'error' => 'Failed to copy file to final storage'
        ];
      }

      // Store in database now that upload is complete
      $fileId = $this->storeInDatabase($uploadInfo, $copyResult);

      if (!$fileId) {
        error_log("Failed to store file in database for upload: " . substr($uploadId, 0, 10));
        return [
          'error' => 'Failed to store file in database'
        ];
      }

      // Get the complete file data for frontend
      $fileData = $this->getFileDataById($fileId, $uploadInfo, $copyResult);

      //error_log("Completed multipart upload: $uploadId for user: $userNpub");

      return [
        'location' => $result['Location'],
        'bucket' => $result['Bucket'],
        'key' => $result['Key'],
        'etag' => $result['ETag'],
        'fileData' => $fileData
      ];
    } catch (AwsException $e) {
      error_log('AWS Error completing multipart upload: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error completing multipart upload: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * List uploaded parts for a multipart upload
   * 
   * @param string $uploadId Upload ID
   * @param string $key Object key
   * @param string $userNpub User's npub
   * @return array|false List of parts or false on failure. Returns special array with 'completed' flag if upload was already completed.
   */
  public function listParts(string $uploadId, string $key, string $userNpub)
  {
    try {
      // Validate user owns this upload
      if (!$this->validateUserOwnership($uploadId, $userNpub, $key)) {
        throw new Exception('User does not own this upload');
      }

      // List all parts, handling truncated responses
      $parts = [];
      $params = [
        'Bucket' => $this->bucket,
        'Key' => $key,
        'UploadId' => $uploadId
      ];
      do {
        $result = $this->s3Client->listParts($params);
        if (isset($result['Parts'])) {
          foreach ($result['Parts'] as $part) {
            $parts[] = [
              'PartNumber' => $part['PartNumber'],
              'ETag' => $part['ETag'],
              'Size' => $part['Size'],
              'LastModified' => $part['LastModified']->format('c')
            ];
          }
        }
        // If truncated, set Marker for next request
        if (!empty($result['IsTruncated']) && !empty($result['NextPartNumberMarker'])) {
          $params['PartNumberMarker'] = $result['NextPartNumberMarker'];
        } else {
          break;
        }
      } while (true);

      $uploadIdShort = substr($uploadId, 0, 10);
      $userNpubShort = substr($userNpub, 0, 10);
      error_log("Listed " . count($parts) . " parts for upload: $uploadIdShort, user: $userNpubShort");

      return $parts;
    } catch (AwsException $e) {
      // Check for NoSuchUpload error - this indicates the upload was likely completed and cleaned up
      if (strpos($e->getAwsErrorCode(), 'NoSuchUpload') !== false) {
        $uploadIdShort = substr($uploadId, 0, 10);
        error_log("Upload not found in S3, checking if already completed: $uploadIdShort");
        
        // Check if upload was completed in S3 and/or database
        $completionStatus = $this->checkForCompletedUpload($key, $userNpub);
        if ($completionStatus) {
          if ($completionStatus['status'] === 'fully_completed') {
            // File is fully completed - return file data
            return [
              'completed' => true,
              'fileData' => $completionStatus
            ];
          } elseif ($completionStatus['status'] === 's3_completed_needs_processing') {
            // S3 object exists but needs processing - tell client to call completion
            error_log("S3 object exists but not processed for upload: $uploadIdShort - instructing client to call completion");
            return [
              'call_completion' => true,
              'key' => $completionStatus['key'],
              'uploadInfo' => $completionStatus['uploadInfo']
            ];
          }
        }
      }
      
      error_log('AWS Error listing parts: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error listing parts: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Abort multipart upload
   * 
   * @param string $uploadId Upload ID
   * @param string $key Object key
   * @param string $userNpub User's npub
   * @return bool Success
   */
  public function abortMultipartUpload(string $uploadId, string $key, string $userNpub): bool
  {
    try {
      // Validate user owns this upload
      if (!$this->validateUserOwnership($uploadId, $userNpub, $key)) {
        throw new Exception('User does not own this upload');
      }

      // Abort the multipart upload
      $this->s3Client->abortMultipartUpload([
        'Bucket' => $this->bucket,
        'Key' => $key,
        'UploadId' => $uploadId
      ]);

      $uploadIdShort = substr($uploadId, 0, 10);
      $userNpubShort = substr($userNpub, 0, 10);
      error_log("Aborted multipart upload: $uploadIdShort for user: $userNpubShort");

      return true;
    } catch (AwsException $e) {
      error_log('AWS Error aborting multipart upload: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error aborting multipart upload: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Generate unique key for upload
   * 
   * @param string $filename Original filename
   * @param string $userNpub User's npub
   * @param string $mimeType MIME type of the file
   * @return string Unique key
   */
  private function generateUploadKey(string $filename, string $userNpub, string $mimeType): string
  {
    // Get the correct extension from MIME type using our utils function
    $accountLevel = (int)$_SESSION['acctlevel'] ?? 0;
    $allowedMimes = getAllowedMimesArray($accountLevel);
    $fileExtension = $allowedMimes[$mimeType] ?? null;

    // Fallback to original filename extension if MIME type not found
    if (!$fileExtension) {
      $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
      error_log("Warning: MIME type '$mimeType' not found in allowed types, using original extension: $fileExtension");
    }

    $nanoid = getUniqueNanoId();
    $finalFilename = $fileExtension ? "{$nanoid}." . strtolower($fileExtension) : $nanoid;
    return "uploads/{$userNpub}/" . $finalFilename;
  }

  /**
   * Validate user owns this upload by checking key structure
   * 
   * @param string $uploadId Upload ID  
   * @param string $userNpub User's npub
   * @param string|null $key Optional object key for validation
   * @return bool True if user owns upload
   */
  private function validateUserOwnership(string $uploadId, string $userNpub, ?string $key = null): bool
  {
    // If we have a key, validate directly from it
    if ($key) {
      $expectedPrefix = "uploads/{$userNpub}/";
      return str_starts_with($key, $expectedPrefix);
    }

    // For methods that don't have the key, we'll use a different approach

    try {
      // List multipart uploads for this user's prefix to find the upload
      $result = $this->s3Client->listMultipartUploads([
        'Bucket' => $this->bucket,
        'Prefix' => "uploads/{$userNpub}/",
        'MaxUploads' => 100
      ]);

      if (isset($result['Uploads'])) {
        foreach ($result['Uploads'] as $upload) {
          if ($upload['UploadId'] === $uploadId) {
            return true;
          }
        }
      }

      return false;
    } catch (AwsException $e) {
      error_log('AWS Error validating user ownership: ' . $e->getMessage());
      return false;
    } catch (Exception $e) {
      error_log('Error validating user ownership: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Get upload info from S3 metadata (replaces session storage)
   * 
   * @param string $uploadId Upload ID (not used but kept for API compatibility)
   * @param string $key Object key  
   * @return array|false Upload info matching original session structure
   */
  private function getUploadInfoFromS3(string $uploadId, string $key)
  {
    try {
      // Get the completed object's metadata which contains our original upload info
      $objectMetadata = $this->s3Service->getObjectMetadata($this->bucket, $key);

      if (!$objectMetadata || !isset($objectMetadata['Metadata'])) {
        error_log("No metadata found for object: " . substr($key, 0, 10));
        return false;
      }

      $metadata = $objectMetadata['Metadata'];

      // Extract user npub from key path as fallback
      $pathParts = explode('/', $key);
      $userNpubFromPath = (count($pathParts) >= 3 && $pathParts[0] === 'uploads') ? $pathParts[1] : '';

      // Reconstruct the original session structure
      return [
        'key' => $key,
        'filename' => $metadata['original-filename'] ?? basename($key),
        'contentType' => $objectMetadata['ContentType'] ?? 'application/octet-stream',
        'userNpub' => $metadata['user-npub'] ?? $userNpubFromPath,
        'metadata' => isset($metadata['metadata']) ? json_decode($metadata['metadata'], true) : [],
        'createdAt' => isset($metadata['upload-time']) ? (int)$metadata['upload-time'] : time()
      ];
    } catch (Exception $e) {
      error_log('Error getting upload info from S3: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Copy completed upload to final storage bucket and return new filename
   * 
   * @param string $key Source object key
   * @param array $uploadInfo Upload information
   * @return array|false Array with new filename and metadata, or false on failure
   */
  private function copyToFinalStorage(string $key, array $uploadInfo)
  {
    try {
      // Get object metadata from upload bucket
      $metadata = $this->s3Service->getObjectMetadata($this->bucket, $key);
      if (!$metadata) {
        throw new Exception("Failed to get object metadata from upload bucket");
      }

      // Extract the existing filename from the upload key (reuse the same filename)
      $originalFilename = $uploadInfo['filename'];
      $mimeType = $metadata['ContentType'];
      
      // Extract filename from the key path (uploads/npub/filename.ext)
      $keyParts = explode('/', $key);
      $newFilename = end($keyParts); // Get the last part which is the filename

      // Create destination path like "uploads/npub.../filename.ext" 
      $destinationKey = "uploads/{$uploadInfo['userNpub']}/{$newFilename}";

      // Copy to final R2 storage (pro account = true as specified)
      $copyResult = $this->s3Service->copyToFinalR2Storage(
        $this->bucket,  // source bucket
        $key,           // source key
        $destinationKey, // destination key with path
        $mimeType,      // mime type
        true,           // professional account
        [               // additional metadata
          'original-filename' => $originalFilename,
          'npub' => $uploadInfo['userNpub'],
          'file-size' => (string)$metadata['ContentLength'],
          'upload-method' => 'multipart-s3'
        ]
      );

      if (!$copyResult) {
        throw new Exception("Failed to copy to final R2 storage");
      }

      return [
        'filename' => $newFilename,
        'fileSize' => $metadata['ContentLength'],
        'mimeType' => $mimeType,
        'etag' => $metadata['ETag'],
        'destinationKey' => $destinationKey,
        'checksum_sha256' => $copyResult['checksum_sha256'] ?? null
      ];
    } catch (Exception $e) {
      error_log('Error copying to final storage: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Store upload information in database
   * 
   * @param array $uploadInfo Upload information
   * @param array $fileData File data from copy operation
   * @return int|false Insert ID on success, false on failure
   */
  private function storeInDatabase(array $uploadInfo, array $fileData)
  {
    try {
      // Handle folder information from metadata
      $folder_id = null;
      $folder_name = null;

      // Extract folder information from metadata if present
      if (!empty($uploadInfo['metadata']['folderName'])) {
        $folder_name = json_decode($uploadInfo['metadata']['folderName']) ?? '';
      }

      // Find or create folder if we have folder information
      if (!empty($folder_name)) {
        try {
          $folder_id = $this->usersImagesFolders->findFolderByNameOrCreate($uploadInfo['userNpub'], $folder_name);
        } catch (Exception $e) {
          error_log('Error finding/creating folder: ' . $e->getMessage());
          // Continue without folder if there's an error
        }
      }

      // Insert into users_images table with proper data
      $insertId = $this->usersImages->insert([
        'usernpub' => $uploadInfo['userNpub'],
        'sha256_hash' => null, // $fileData['checksum_sha256'] ?: $fileData['etag'], // Use actual SHA256 checksum from R2, fallback to ETag
        'image' => $fileData['filename'],
        'file_size' => $fileData['fileSize'],
        'flag' => 0, // Private by default
        'folder_id' => $folder_id,
        'mime_type' => $fileData['mimeType'],
        'title' => $uploadInfo['filename'], // Keep original filename as title
        'media_width' => 0, // TODO: Extract from metadata if image/video
        'media_height' => 0, // TODO: Extract from metadata if image/video
        'blurhash' => null // TODO: Generate blurhash if image
      ]);

      if ($insertId) {
        $folderInfo = $folder_id ? " in folder ID $folder_id" : " (no folder)";
        $filenameShort = substr($fileData['filename'], 0, 10);
        $userNpubShort = substr($uploadInfo['userNpub'], 0, 10);
        error_log("Stored multipart upload in database: ID $insertId, filename: {$filenameShort} for user: {$userNpubShort}$folderInfo");
        // Send upload hook
        // We only accept two types now, video or archive, TODO: Update for new types
        $fileType = str_starts_with($fileData['mimeType'], 'video/') ? 'video' : 'archive';
        $fileTooLarge = $fileData['fileSize'] > 8 * 1024 ** 2 ** 1024; // 
        $doVirusScan = in_array($fileType, ['archive', 'document', 'text', 'other']) && !$fileTooLarge;
        $nameWithoutExtension = pathinfo($fileData['filename'], PATHINFO_FILENAME);
        $this->uploadWebhook->createPayload(
          fileHash: $nameWithoutExtension,
          fileName: $fileData['filename'],
          fileSize: $fileData['fileSize'],
          fileMimeType: $fileData['mimeType'],
          fileUrl: $this->generateCdnUrl($fileData['filename'], $fileData['mimeType']),
          fileType: $fileType,
          shouldTranscode: false,
          uploadAccountType: 'subscriber',
          uploadTime: time(),
          uploadedFileInfo: $_SERVER['CLIENT_REQUEST_INFO'] ?? null,
          uploadNpub: $uploadInfo['userNpub'] ?? null,
          fileOriginalUrl: null,
          doVirusScan: $doVirusScan, // TODO: Figureout how to deal with oversized archives that are hard to scan.
          orginalSha256Hash: $fileData['checksum_sha256'] ?? null,
          currentSha256Hash: $fileData['checksum_sha256'] ?? null,
        );
        $this->uploadWebhook->sendPayload();
        return $insertId;
      }

      return false;
    } catch (Exception $e) {
      error_log('Error storing in database: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Get file data by ID for frontend
   * 
   * @param int $fileId File ID
   * @param array $uploadInfo Upload information
   * @param array $copyResult Copy result data
   * @return array File data structure
   */
  private function getFileDataById(int $fileId, array $uploadInfo, array $copyResult): array
  {
    try {
      // Get file from database
      $fileRecord = $this->usersImages->get($fileId);

      // Return file data in the format expected by frontend
      return [
        'id' => (string)$fileRecord['id'],
        'name' => $fileRecord['image'],
        'title' => $fileRecord['title'] ?? $fileRecord['image'],
        'description' => $fileRecord['description'] ?? '',
        'mime' => $fileRecord['mime_type'],
        'media_type' => explode('/', $fileRecord['mime_type'])[0], // 'image', 'video', etc.
        'size' => (int)$fileRecord['file_size'],
        'width' => (int)($fileRecord['media_width'] ?? 0),
        'height' => (int)($fileRecord['media_height'] ?? 0),
        'url' => $this->generateCdnUrl($fileRecord['image'], $fileRecord['mime_type']),
        'folder' => $fileRecord['folder_id'] ? $this->getFolderName($fileRecord['folder_id']) : '',
        'created_at' => $fileRecord['created_at'] ?? date('Y-m-d H:i:s'),
        'flag' => (int)$fileRecord['flag'],
        'blurhash' => $fileRecord['blurhash']
      ];
    } catch (Exception $e) {
      error_log('Error getting file data: ' . $e->getMessage());

      // Fallback if database record not found - use copy result data
      return [
        'id' => (string)$fileId,
        'name' => $copyResult['filename'],
        'title' => $uploadInfo['filename'],
        'description' => '',
        'mime' => $copyResult['mimeType'],
        'media_type' => explode('/', $copyResult['mimeType'])[0],
        'size' => (int)$copyResult['fileSize'],
        'width' => 0,
        'height' => 0,
        'url' => $this->generateCdnUrl($copyResult['filename'], $copyResult['mimeType']),
        'folder' => '',
        'created_at' => date('Y-m-d H:i:s'),
        'flag' => 0,
        'blurhash' => null
      ];
    }
  }

  /**
   * Get the correct CDN URL based on media type
   * 
   * @param string $filename Filename
   * @param string $mimeType MIME type
   * @return string CDN URL
   */
  private function generateCdnUrl(string $filename, string $mimeType): string
  {
    $mediaType = explode('/', $mimeType)[0];

    switch ($mediaType) {
      case 'video':
        return "https://v.nostr.build/{$filename}";
      case 'image':
      case 'audio':
      default:
        return "https://d.nostr.build/{$filename}";
    }
  }

  /**
   * Get folder name by ID
   * 
   * @param int $folderId Folder ID
   * @return string Folder name
   */
  private function getFolderName(int $folderId): string
  {
    // This would need to be implemented based on your folder structure
    // For now, return empty string
    return $this->usersImagesFolders->getFolderNameById($this->userNpub, $folderId);
  }

  /**
   * Check if object exists in S3 upload bucket
   * 
   * @param string $key Object key
   * @return bool True if object exists
   */
  private function checkS3ObjectExists(string $key): bool
  {
    try {
      $this->s3Client->headObject([
        'Bucket' => $this->bucket,
        'Key' => $key
      ]);
      return true;
    } catch (AwsException $e) {
      if ($e->getAwsErrorCode() === 'NotFound') {
        return false;
      }
      // Re-throw other AWS errors
      throw $e;
    }
  }

  /**
   * Check completion status with comprehensive S3 and database validation
   * 
   * @param string $key Object key
   * @param string $userNpub User's npub
   * @return array Completion status with action needed
   */
  public function checkForCompletedUpload(string $key, string $userNpub): ?array
  {
    try {
      // Extract filename from key (uploads/npub/filename.ext -> filename.ext)
      $filename = basename($key);
      $filenameShort = substr($filename, 0, 10);
      $userNpubShort = substr($userNpub, 0, 10);
      
      // Step 1: Check if file exists in database (fully completed)
      $dbFile = $this->usersImages->findByFilenameAndUser($filename, $userNpub);
      
      if ($dbFile) {
        error_log("Found completed multipart upload in database: {$filenameShort} for user: {$userNpubShort}");
        
        // Return file data - fully completed
        return [
          'status' => 'fully_completed',
          'id' => (string)$dbFile['id'],
          'name' => $dbFile['image'],
          'title' => $dbFile['title'] ?? $dbFile['image'],
          'description' => $dbFile['description'] ?? '',
          'mime' => $dbFile['mime_type'],
          'media_type' => explode('/', $dbFile['mime_type'])[0],
          'size' => (int)$dbFile['file_size'],
          'width' => (int)($dbFile['media_width'] ?? 0),
          'height' => (int)($dbFile['media_height'] ?? 0),
          'url' => $this->generateCdnUrl($dbFile['image'], $dbFile['mime_type']),
          'folder' => $dbFile['folder_id'] ? $this->getFolderName($dbFile['folder_id']) : '',
          'created_at' => $dbFile['created_at'] ?? date('Y-m-d H:i:s'),
          'flag' => (int)$dbFile['flag'],
          'blurhash' => $dbFile['blurhash'],
          'associated_notes' => $dbFile['associated_notes'] ?? ''
        ];
      }
      
      // Step 2: Check if object exists in S3 upload bucket (uploaded but not processed)
      if ($this->checkS3ObjectExists($key)) {
        error_log("Found completed S3 object but not in database: {$filenameShort} for user: {$userNpubShort} - triggering re-completion");
        
        // Get upload info from S3 metadata to prepare for re-completion
        $uploadInfo = $this->getUploadInfoFromS3('', $key); // uploadId not needed for this call
        
        if ($uploadInfo) {
          // Return status indicating we need to re-trigger completion
          return [
            'status' => 's3_completed_needs_processing',
            'key' => $key,
            'uploadInfo' => $uploadInfo,
            'filename' => $filename
          ];
        }
      }
      
      // Step 3: Neither in DB nor S3 - truly not completed
      return null;
      
    } catch (Exception $e) {
      error_log('Error checking for completed upload: ' . $e->getMessage());
      return null;
    }
  }

}
