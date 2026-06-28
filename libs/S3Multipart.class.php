<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/CloudflareUploadWebhook.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/LegacyBlacklist.class.php';
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
  private $awsConfig;

  /**
   * Constructor
   * 
   * @param array $awsConfig AWS configuration array
   * @param mysqli $db Database connection
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

    $this->awsConfig = $awsConfig;
    $this->s3Client = new S3Client($awsConfig['upload']);
    $this->bucket = $awsConfig['upload']['bucket'];
    $this->db = $db;
    $this->usersImages = new UsersImages($db);
    $this->usersImagesFolders = new UsersImagesFolders($db);
    $this->s3Service = new S3Service($awsConfig);
    $this->userNpub = $_SESSION['usernpub'] ?? '';
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
  public function createMultipartUpload(string $filename, string $contentType, array $metadata, string $userNpub, string $userUuid = '')
  {
    try {
      // The stable uuid is the identity that keys the upload (an npub-less email
      // account has no npub). npub stays optional and only drives the npub-keyed
      // ban check below.
      if ($userUuid === '') {
        throw new Exception('userUuid is required');
      }

      // Hard-fail banned npubs before we hand out any presigned URLs.
      // Until this gate landed, the entire /s3/multipart path bypassed
      // every blacklist check (UploadValidator only runs inside
      // MultimediaUpload — S3Multipart is its own pipeline). A banned npub
      // could keep uploading large files via the dashboard's multipart flow
      // even after their npub was blacklisted on every other route.
      // The check uses the legacy `blacklist` table which both abuse and
      // CSAM bans write to. It is npub-keyed, so it only applies to accounts
      // that have an npub; an email-only account is gated by isLocked/isExpired.
      if ($userNpub !== '' && (new LegacyBlacklist($this->db))->isNpubBanned($userNpub)) {
        error_log('S3Multipart: blocked banned npub ' . $userNpub);
        throw new Exception('User has been flagged as rejected');
      }

      // Validate user account — resolve from npub when present, else the uuid.
      $account = $userNpub !== '' ? new Account($userNpub, $this->db) : Account::fromUuid($userUuid, $this->db);
      if ($account === null || !$account->accountExists()) {
        throw new Exception('Account not found');
      }
      // Ban gate, email-aware: isBanned() checks the npub blacklist AND the email
      // blacklist, so a banned key-less account is blocked here too (the npub-only
      // fast check above can't reach it).
      if ($account->isBanned()) {
        error_log('S3Multipart: blocked banned account ' . $userUuid);
        throw new Exception('User has been flagged as rejected');
      }
      // Legal-hold lockout (CSAM evidence preservation) AND expiry both block
      // large uploads. isLocked covers the uuid-keyed termination path that an
      // npub-less account would otherwise miss (the npub blacklist can't reach it).
      if ($account->isLocked()) {
        throw new Exception('Account is locked');
      }
      if ($account->isExpired()) {
        throw new Exception('Account has expired');
      }

      // Generate unique key for the upload — uuid-prefixed so an npub-less
      // account is addressable. The Worker's ownsKey accepts both prefixes.
      $key = $this->generateUploadKey($filename, $userUuid, $contentType);

      // Create multipart upload
      $result = $this->s3Client->createMultipartUpload([
        'Bucket' => $this->bucket,
        'Key' => $key,
        'ContentType' => $contentType,
        'Metadata' => [
          'original-filename' => $filename,
          'user-npub' => $userNpub,
          'user-uuid' => $userUuid,
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
   * Complete multipart upload
   * 
   * @param string $uploadId Upload ID
   * @param string $key Object key
   * @param array $parts Array of parts with PartNumber and ETag
   * @param string $userNpub User's npub
   * @return array|false Success data or false on failure
   */
  public function completeMultipartUpload(string $uploadId, string $key, array $parts, string $userNpub, string $userUuid = '')
  {
    try {
      if ($userUuid === '') {
        throw new Exception('userUuid is required');
      }
      // Validate user owns this upload — staging keys are strictly uuid-prefixed.
      if (!$this->validateUserOwnership($key, $userUuid)) {
        throw new Exception('User does not own this upload');
      }

      // Re-check the ban list at completion. Catches the case where a user
      // created the multipart upload before being banned and is now trying to
      // publish the assembled file. npub → cheap indexed check; key-less account
      // → resolve from uuid and ask isBanned() (which also covers the email
      // list). Best-effort cleanup of the S3 parts on abort below — even if
      // cleanup fails, the file never becomes publicly accessible because we
      // don't run the copy step or insert the DB row.
      $bannedAtComplete = false;
      if ($userNpub !== '') {
        $bannedAtComplete = (new LegacyBlacklist($this->db))->isNpubBanned($userNpub);
      } else {
        $acct = Account::fromUuid($userUuid, $this->db);
        $bannedAtComplete = $acct !== null && $acct->isBanned();
      }
      if ($bannedAtComplete) {
        error_log('S3Multipart: blocked banned account at complete; aborting upload ' . substr($uploadId, 0, 10));
        try {
          $this->s3Client->abortMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
          ]);
        } catch (\Throwable $abortError) {
          error_log('S3Multipart: abort after ban-check failure: ' . $abortError->getMessage());
        }
        throw new Exception('User has been flagged as rejected');
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

      // Auto-extract video poster (best-effort, never fails the upload)
      if (str_starts_with($copyResult['mimeType'], 'video/')) {
        try {
          require_once __DIR__ . '/VideoPosterExtractor.class.php';

          // Generate presigned URL from R2 final storage for ffmpeg to read
          $posterBucket = $this->awsConfig['r2']['bucket']
            . SiteConfig::getBucketSuffix('professional_account_video');
          $presignedUrl = getPresignedUrlFromObjectKey(
            $copyResult['filename'],
            $this->awsConfig['r2']['endpoint'],
            $this->awsConfig['r2']['credentials']['key'],
            $this->awsConfig['r2']['credentials']['secret'],
            $posterBucket,
            300
          );

          if (!empty($presignedUrl)) {
            $posterExtractor = new VideoPosterExtractor($this->awsConfig, $this->usersImages);
            $posterExtractor->extractAndUpload(
              $presignedUrl,
              $copyResult['filename'],
              $fileId,
              $uploadInfo['userNpub']
            );
          }
        } catch (\Throwable $e) {
          error_log("Auto poster extraction (multipart) failed: " . $e->getMessage());
        }
      }

      // Get the complete file data for frontend
      $fileData = $this->getFileDataById($fileId, $uploadInfo, $copyResult);

      return [
        'location' => isset($result) ? $result['Location'] : null,
        'bucket' => isset($result) ? $result['Bucket'] : $this->bucket,
        'key' => $key,
        'etag' => isset($result) ? $result['ETag'] : ($copyResult['etag'] ?? null),
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
   * Generate unique key for upload — strictly uuid-prefixed (the stable identity
   * every account has). `uploads/{uuid}/{nanoid}.{ext}`.
   *
   * @param string $filename Original filename
   * @param string $userUuid Owner's stable uuid
   * @param string $mimeType MIME type of the file
   * @return string Unique key
   */
  private function generateUploadKey(string $filename, string $userUuid, string $mimeType): string
  {
    // Get the correct extension from MIME type using our utils function
    $accountLevel = (int)($_SESSION['acctlevel'] ?? 0);
    $allowedMimes = getAllowedMimesArray($accountLevel);
    $fileExtension = $allowedMimes[$mimeType] ?? null;

    // Fallback to original filename extension if MIME type not found
    if (!$fileExtension) {
      $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
      error_log("Warning: MIME type '$mimeType' not found in allowed types, using original extension: $fileExtension");
    }

    $nanoid = getUniqueNanoId();
    $finalFilename = $fileExtension ? "{$nanoid}." . strtolower($fileExtension) : $nanoid;
    return "uploads/{$userUuid}/" . $finalFilename;
  }

  /**
   * Validate the caller owns this upload by its key prefix. Staging keys are
   * minted STRICTLY uuid-prefixed (generateUploadKey), so ownership is the stable
   * uuid and nothing else. The trailing slash is required so a sibling prefix
   * (`uploads/{uuid}EVIL/…`) can't pass, and an empty uuid never matches.
   *
   * @param string $key Object key
   * @param string $userUuid Owner's stable uuid
   * @return bool True if the caller owns the upload
   */
  private function validateUserOwnership(string $key, string $userUuid): bool
  {
    return $userUuid !== '' && str_starts_with($key, "uploads/{$userUuid}/");
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

      // Extract the user prefix from the key path as a fallback. Keys are now
      // uuid-prefixed (uploads/{uuid}/...), but a key minted before the cutover
      // is npub-prefixed — so this segment is the uuid for new uploads and the
      // npub for old ones. The S3 metadata (user-uuid / user-npub) is the
      // reliable source; the path is only used when metadata is missing.
      $pathParts = explode('/', $key);
      $userPrefixFromPath = (count($pathParts) >= 3 && $pathParts[0] === 'uploads') ? $pathParts[1] : '';
      // A pre-cutover key is npub-prefixed; a post-cutover key is uuid-prefixed.
      // Only treat the path segment as a uuid when it is NOT an npub — otherwise
      // a legacy npub prefix would short-circuit storeInDatabase's resolver and
      // land the npub in users_images.user_uuid, corrupting ownership.
      $prefixIsNpub = str_starts_with($userPrefixFromPath, 'npub1');

      // Reconstruct the original session structure
      return [
        'key' => $key,
        'filename' => $metadata['original-filename'] ?? basename($key),
        'contentType' => $objectMetadata['ContentType'] ?? 'application/octet-stream',
        'userNpub' => $metadata['user-npub'] ?? ($prefixIsNpub ? $userPrefixFromPath : ''),
        // uuid is the owner identity. Prefer the metadata; fall back to the path
        // segment ONLY when it's a uuid (new key). For a legacy npub-prefixed key
        // leave this empty so storeInDatabase resolves npub→uuid via the resolver.
        'userUuid' => $metadata['user-uuid'] ?? ($prefixIsNpub ? '' : $userPrefixFromPath),
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
      
      // Extract filename from the key path (uploads/{uuid}/filename.ext)
      $keyParts = explode('/', $key);
      $newFilename = end($keyParts); // Get the last part which is the filename

      // Destination prefix is uuid-keyed (an email account has no npub). The
      // prefix is cosmetic for serving — S3Service::getR2BucketAndObjectNames
      // basenames the key, so the final object + CDN URL are filename-only.
      $destinationKey = "uploads/{$uploadInfo['userUuid']}/{$newFilename}";

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

      // Resolve the owner uuid: the upload now carries it directly (uuid-keyed),
      // falling back to resolving it from the npub for a pre-cutover upload.
      $userUuid = !empty($uploadInfo['userUuid'])
        ? $uploadInfo['userUuid']
        : resolveOwnerUuid($this->usersImages->getDb(), $uploadInfo['userNpub']);
      if ($userUuid === null || $userUuid === '') {
        throw new Exception('Unable to resolve user uuid for multipart upload');
      }

      // Find or create folder if we have folder information. Folders are keyed by
      // the stable uuid (findFolderByNameOrCreate resolves owner→uuid), so the
      // uuid works for npub and email-only accounts alike.
      if (!empty($folder_name)) {
        try {
          $folder_id = $this->usersImagesFolders->findFolderByNameOrCreate($userUuid, $folder_name);
        } catch (Exception $e) {
          error_log('Error finding/creating folder: ' . $e->getMessage());
          // Continue without folder if there's an error
        }
      }
      // users_images is keyed by the stable user_uuid; the legacy `usernpub`
      // column was dropped from the table in the npub→uuid re-key, so inserting
      // it here threw "Unknown column 'usernpub'" and left the file copied to
      // final storage with no DB row. The npub stays available via $uploadInfo
      // for the upload webhook below.
      $insertId = $this->usersImages->insert([
        'user_uuid' => $userUuid,
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
        $fileTooLarge = $fileData['fileSize'] > 8 * 1024 * 1024; // 8 MB
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
