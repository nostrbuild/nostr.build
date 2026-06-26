<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once __DIR__ . '/BlossomFrontEndAPI.class.php';

/**
 * Class ImageManager
 * Handles image and folder management operations
 */
class ImageCatalogManager
{
  private $link;
  private $s3;
  private $usernpub;
  private $userUuid;
  private $cloudflarePurger;
  private $blossomFrontEndAPI;
  private $fromAPI;

  /**
   * ImageManager constructor.
   * @param mysqli $link Database connection
   * @param mixed $s3 S3 service instance
   * @param string $usernpub User identifier
   */
  public function __construct(mysqli $link, mixed $s3, string $owner, ?bool $fromAPI = false)
  {
    $this->link = $link;
    $this->s3 = $s3;
    // $owner may be a uuid (Worker path) or an npub (legacy callers). The derived
    // tables key on the stable uuid; Blossom is still npub-keyed, so keep both.
    $this->userUuid = resolveOwnerUuid($link, $owner);
    $this->usernpub = str_starts_with($owner, 'npub1') ? $owner : (uuidToNpub($link, $owner) ?? '');
    $this->cloudflarePurger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
    $this->blossomFrontEndAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
    $this->fromAPI = $fromAPI;
  }

  public function getImageByName(string $imageName): array | null
  {
    $stmt = $this->link->prepare("SELECT * FROM users_images WHERE user_uuid = ? AND image LIKE ? ESCAPE '\\\\' LIMIT 1");
    $imageName = $imageName . '.%';
    $stmt->bind_param('ss', $this->userUuid, $imageName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
  }

  public function getImageByBlossomHash(string $blossomHash): array | null
  {
    $stmt = $this->link->prepare("SELECT * FROM users_images WHERE user_uuid = ? AND blossom_hash = ? LIMIT 1");
    $stmt->bind_param('ss', $this->userUuid, $blossomHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
  }

  /**
   * Delete image records
   * @param array $imageIds Array of image IDs to delete
   * @return array List of files that were deleted successfully
   */
  public function deleteImages(array $imageIds): array
  {
    try {
      $res = $this->deleteImageRecords($imageIds);
      $filesToPurge = $res[0];
      $imageIds = $res[1];

      if (!empty($filesToPurge)) {
        $this->purgeCloudflareCache($filesToPurge);
      }

      return $imageIds;
    } catch (Exception $e) {
      error_log("Error occurred while deleting images: " . $e->getMessage() . "\n");
      return [];
    }
  }

  /**
   * Delete folder records
   * @param array $folderIds Array of folder IDs to delete
   * @return array Liat of folders that were deleted successfully
   */
  public function deleteFolders(array $folderIds): array
  {

    try {
      $res = $this->deleteFolderRecords($folderIds);
      return $res;
    } catch (Exception $e) {
      error_log("Error occurred while deleting folders: " . $e->getMessage() . "\n");
      return [];
    }
  }

  /**
   * Delete image records from the database
   * @param array $imageIds Array of image IDs to delete
   * @return array Array of file paths to purge from Cloudflare
   * @throws Exception If file deletion fails
   */
  private function deleteImageRecords(array $imageIds): array
  {
    $filesToPurge = [];

    if (empty($imageIds)) {
      return $filesToPurge;
    }

    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $selectStmt = $this->link->prepare("SELECT * FROM users_images WHERE user_uuid = ? AND id IN ($placeholders)");
    $selectStmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->userUuid, ...$imageIds);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $deleteStmt = null;
    $inTransaction = false;

    try {
      while ($row = $result->fetch_assoc()) {
        $file_name = basename(strpos($row['image'], '://') === false ? $row['image'] : parse_url($row['image'], PHP_URL_PATH));
        $file_type = getFileTypeFromName($file_name);
        $s3_file_path = SiteConfig::getS3Path('professional_account_' . $file_type) . $file_name;

        if ($this->s3->getObjectMetadataFromR2(objectKey: $s3_file_path, paidAccount: true, mime: $row['mime_type']) !== false) {
          error_log("Deleting file from S3: " . $s3_file_path . PHP_EOL);

          try {
            $this->s3->deleteFromS3(objectKey: $s3_file_path, paidAccount: true, mimeType: $row['mime_type']);
            $filesToPurge[] = $row['image'];
          } catch (Aws\S3\Exception\S3Exception $e) {
            if ($e->getAwsErrorCode() !== 'NoSuchKey') {
              throw new Exception("File deletion failed for: " . $s3_file_path, 0, $e);
            }
          }
        }
        // Handle Blossom media deletion
        if (!empty($row['blossom_hash']) && !$this->fromAPI) {
          $this->blossomFrontEndAPI->deleteMedia($this->usernpub, $row['blossom_hash']);
        }
      }

      $this->link->begin_transaction();
      $inTransaction = true;
      $deleteStmt = $this->link->prepare("DELETE FROM users_nostr_images WHERE user_uuid = ? AND image_id IN ($placeholders)");
      if (!$deleteStmt) {
        throw new Exception('Failed to prepare users_nostr_images delete');
      }
      $deleteStmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->userUuid, ...$imageIds);
      if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete users_nostr_images rows: ' . $deleteStmt->error);
      }
      $deleteStmt->close();
      $deleteStmt = null;

      // Then the image rows. users_nostr_images has an ON DELETE CASCADE FK on
      // image_id, so this delete also clears the note↔image links; the explicit
      // delete above just makes the owner-scoped cleanup order obvious. Both run
      // in one transaction so they roll back together.
      $deleteStmt = $this->link->prepare("DELETE FROM users_images WHERE user_uuid = ? AND id IN ($placeholders)");
      if (!$deleteStmt) {
        throw new Exception('Failed to prepare users_images delete');
      }
      $deleteStmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->userUuid, ...$imageIds);
      if (!$deleteStmt->execute()) {
        throw new Exception('Failed to delete users_images rows: ' . $deleteStmt->error);
      }
      $this->link->commit();
      $inTransaction = false;
    } catch (Throwable $e) {
      if ($inTransaction) {
        $this->link->rollback();
      }
      throw $e;
    } finally {
      if ($selectStmt instanceof mysqli_stmt) {
        $selectStmt->close();
      }
      if ($deleteStmt instanceof mysqli_stmt) {
        $deleteStmt->close();
      }
    }

    return [$filesToPurge, $imageIds];
  }

  /**
   * Delete folder records from the database
   * @param array $folderIds Array of folder IDs to delete
   * @return array List of folders that were deleted successfully
   */
  private function deleteFolderRecords(array $folderIds): array
  {
    if (empty($folderIds)) {
      return [];
    }

    try {
      $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
      // FK constraint will NULL out the folder_id in users_images table
      $stmt = $this->link->prepare("DELETE FROM users_images_folders WHERE user_uuid = ? AND id IN ($placeholders)");
      $stmt->bind_param('s' . str_repeat('i', count($folderIds)), $this->userUuid, ...$folderIds);
      $stmt->execute();
      $stmt->close();
    } catch (Exception $e) {
      error_log("Error occurred while deleting folder records: " . $e->getMessage());
      return [];
    }

    return $folderIds;
  }

  /**
   * Purge files from Cloudflare cache
   * @param array $files Array of file paths to purge
   * @return bool True if purge is successful, false otherwise
   */
  private function purgeCloudflareCache(array $files): bool
  {
    try {
      error_log("Purging files from Cloudflare: " . json_encode($files) . PHP_EOL);
      $result = $this->cloudflarePurger->purgeFiles($files);
      if ($result !== false) {
        error_log(json_encode($result));
        return true;
      }
      return false;
    } catch (Exception $e) {
      error_log("PURGE error occurred: " . $e->getMessage() . "\n");
      return false;
    }
  }

  /**
   * Add flag to share images on creator's page
   * @param array $imageIds Array of image IDs
   * @param bool $shareFlag Share flag
   */
  public function shareImage(array $imageIds, bool $shareFlag): array
  {
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $flag = $shareFlag ? '1' : '0';
    error_log("Setting share flag to $flag for images: " . implode(',', $imageIds) . PHP_EOL);
    $stmt = null;
    try {
      $stmt = $this->link->prepare("UPDATE users_images SET flag = ? WHERE user_uuid = ? AND id IN ($placeholders)");
      $stmt->bind_param('ss' . str_repeat('i', count($imageIds)), $flag, $this->userUuid, ...$imageIds);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update share flag");
      }
    } catch (Exception $e) {
      error_log("Error occurred while updating share flag: " . $e->getMessage());
      return [];
    } finally {
      if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
      }
    }
    return $imageIds;
  }

  /**
   * Move images to a different folder
   * @param array $imageIds Array of image IDs to move
   * @param int $folderId Destination folder ID
   */
  public function moveImages(array $imageIds, int $folderId): array
  {
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    error_log("Moving images: " . implode(',', $imageIds) . " to folder: $folderId" . PHP_EOL);
    $stmt = null;
    try {
      // Handle special case where folderId is set to 0, meaning the images are moved to the root folder
      if ($folderId === 0) {
        $stmt = $this->link->prepare("UPDATE users_images SET folder_id = NULL WHERE user_uuid = ? AND id IN ($placeholders)");
        $stmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->userUuid, ...$imageIds);
        if (!$stmt->execute()) {
          throw new Exception("Failed to move images");
        }
        return $imageIds;
      }
      // Update the folder and folder ID for the images
      // FK constraint will prevent moving images to a folder that doesn't exist
      $stmt = $this->link->prepare("UPDATE users_images SET folder_id = ? WHERE user_uuid = ? AND id IN ($placeholders)");
      $stmt->bind_param('is' . str_repeat('i', count($imageIds)), $folderId, $this->userUuid, ...$imageIds);
      if (!$stmt->execute()) {
        throw new Exception("Failed to move images");
      }
    } catch (Exception $e) {
      error_log("Error occurred while moving images: " . $e->getMessage());
      return [];
    } finally {
      if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
      }
    }
    return $imageIds;
  }

  /**
   * Rename a folder
   * @param int $folderId Folder ID
   * @param string $folderName New folder name
   */
  public function renameFolder(int $folderId, string $folderName): array
  {
    error_log("Renaming folder: $folderId to $folderName" . PHP_EOL);
    $stmt = null;
    try {
      $stmt = $this->link->prepare("UPDATE users_images_folders SET folder = ? WHERE user_uuid = ? AND id = ?");
      $stmt->bind_param('ssi', $folderName, $this->userUuid, $folderId);
      if (!$stmt->execute()) {
        throw new Exception("Failed to rename folder");
      }
    } catch (Exception $e) {
      error_log("Error occurred while renaming folder: " . $e->getMessage());
      return [];
    } finally {
      if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
      }
    }
    return [$folderId];
  }

  /**
   * Update media metadata
   *
   * This method updates the metadata (title and description) of an image in the user's image catalog.
   *
   * @param int $imageId The ID of the image to update.
   * @param string|null $title The new title of the image. If not provided, the title will remain unchanged.
   * @param string|null $description The new description of the image. If not provided, the description will remain unchanged.
   * @return array An array containing the updated image ID.
   */
  public function updateMediaMetadata(int $imageId, ?string $title = '', ?string $description = ''): array
  {
    error_log("Updating metadata for image: $imageId" . PHP_EOL);
    $stmt = null;
    try {
      $stmt = $this->link->prepare("UPDATE users_images SET title = ?, description = ? WHERE user_uuid = ? AND id = ?");
      $stmt->bind_param('sssi', $title, $description, $this->userUuid, $imageId);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update metadata");
      }
    } catch (Exception $e) {
      error_log("Error occurred while updating metadata: " . $e->getMessage());
      return [];
    } finally {
      if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
      }
    }
    return [$imageId];
  }
}
