<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

/**
 * Class ImageManager
 * Handles image and folder management operations
 */
class ImageCatalogManager
{
  private $link;
  private $s3;
  private $usernpub;
  private $cloudflarePurger;

  /**
   * ImageManager constructor.
   * @param mysqli $link Database connection
   * @param mixed $s3 S3 service instance
   * @param string $usernpub User identifier
   */
  public function __construct(mysqli $link, mixed $s3, string $usernpub)
  {
    $this->link = $link;
    $this->s3 = $s3;
    $this->usernpub = $usernpub;
    $this->cloudflarePurger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
  }

  public function getImageByName(string $imageName): array | null
  {
    $stmt = $this->link->prepare("SELECT * FROM users_images WHERE usernpub = ? AND image LIKE ? ESCAPE '\\\\' LIMIT 1");
    $imageName = $imageName . '.%';
    $stmt->bind_param('ss', $this->usernpub, $imageName);
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
    $this->link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

    try {
      $res = $this->deleteImageRecords($imageIds);
      $filesToPurge = $res[0];
      $imageIds = $res[1];

      if (!empty($filesToPurge)) {
        $this->purgeCloudflareCache($filesToPurge);
      }

      $this->link->commit();
      return $imageIds;
    } catch (Exception $e) {
      $this->link->rollback();
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
    $this->link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

    try {
      $res = $this->deleteFolderRecords($folderIds);

      $this->link->commit();
      return $res;
    } catch (Exception $e) {
      $this->link->rollback();
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
    $stmt = $this->link->prepare("SELECT * FROM users_images WHERE usernpub = ? AND id IN ($placeholders)");
    $stmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->usernpub, ...$imageIds);
    $stmt->execute();
    $result = $stmt->get_result();

    try {
      while ($row = $result->fetch_assoc()) {
        $file_name = basename(strpos($row['image'], '://') === false ? $row['image'] : parse_url($row['image'], PHP_URL_PATH));
        $file_type = getFileTypeFromName($file_name);
        $s3_file_path = SiteConfig::getS3Path('professional_account_' . $file_type) . $file_name;

        if ($this->s3->getObjectMetadataFromR2(objectKey: $s3_file_path, paidAccount: true, mime: $row['mime_type']) !== false) {
          error_log("Deleting file from S3: " . $s3_file_path . PHP_EOL);

          try {
            $currentSha256 = $this->s3->getS3ObjectHash(objectKey: $s3_file_path, paidAccount: true, mimeType: $row['mime_type']);
            $this->s3->deleteFromS3(objectKey: $s3_file_path, paidAccount: true, mimeType: $row['mime_type']);
            $filesToPurge[] = !empty($currentSha256) ? "{$row['image']}|{$currentSha256}" : $row['image'];
          } catch (Aws\S3\Exception\S3Exception $e) {
            if ($e->getAwsErrorCode() !== 'NoSuchKey') {
              throw new Exception("File deletion failed for: " . $s3_file_path, 0, $e);
            }
          }
        }
      }

      $stmt = $this->link->prepare("DELETE FROM users_images WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->usernpub, ...$imageIds);
      $stmt->execute();
    } finally {
      $stmt->close();
    }

    return [$filesToPurge, $imageIds];
  }

  /**
   * Delete folder records from the database
   * @param array $folderIds Array of folder IDs to delete
   * @return bool List of folders that were deleted successfully
   */
  private function deleteFolderRecords(array $folderIds): array
  {
    if (empty($folderIds)) {
      return [];
    }

    try {
      $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
      // FK constraint will NULL out the folder_id in users_images table
      $stmt = $this->link->prepare("DELETE FROM users_images_folders WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('s' . str_repeat('i', count($folderIds)), $this->usernpub, ...$folderIds);
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
   * @param int $imageId Image ID
   * @param bool $shareFlag Share flag
   */
  public function shareImage(array $imageIds, bool $shareFlag): array
  {
    $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
    $flag = $shareFlag ? '1' : '0';
    error_log("Setting share flag to $flag for images: " . implode(',', $imageIds) . PHP_EOL);
    try {
      $stmt = $this->link->prepare("UPDATE users_images SET flag = ? WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('ss' . str_repeat('i', count($imageIds)), $flag, $this->usernpub, ...$imageIds);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update share flag");
      }
    } catch (Exception $e) {
      error_log("Error occurred while updating share flag: " . $e->getMessage());
      return [];
    } finally {
      $stmt->close();
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
    try {
      // Handle special case where folderId is set to 0, meaning the images are moved to the root folder
      if ($folderId === 0) {
        $stmt = $this->link->prepare("UPDATE users_images SET folder_id = NULL WHERE usernpub = ? AND id IN ($placeholders)");
        $stmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->usernpub, ...$imageIds);
        if (!$stmt->execute()) {
          throw new Exception("Failed to move images");
        }
        return $imageIds;
      }
      // Update the folder and folder ID for the images
      // FK constraint will prevent moving images to a folder that doesn't exist
      $stmt = $this->link->prepare("UPDATE users_images SET folder_id = ? WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('is' . str_repeat('i', count($imageIds)), $folderId, $this->usernpub, ...$imageIds);
      if (!$stmt->execute()) {
        throw new Exception("Failed to move images");
      }
    } catch (Exception $e) {
      error_log("Error occurred while moving images: " . $e->getMessage());
      return [];
    } finally {
      $stmt->close();
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
    try {
      $stmt = $this->link->prepare("UPDATE users_images_folders SET name = ? WHERE usernpub = ? AND id = ?");
      $stmt->bind_param('ssi', $folderName, $this->usernpub, $folderId);
      if (!$stmt->execute()) {
        throw new Exception("Failed to rename folder");
      }
    } catch (Exception $e) {
      error_log("Error occurred while renaming folder: " . $e->getMessage());
      return [];
    } finally {
      $stmt->close();
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
    try {
      $stmt = $this->link->prepare("UPDATE users_images SET title = ?, description = ? WHERE usernpub = ? AND id = ?");
      $stmt->bind_param('sssi', $title, $description, $this->usernpub, $imageId);
      if (!$stmt->execute()) {
        throw new Exception("Failed to update metadata");
      }
    } catch (Exception $e) {
      error_log("Error occurred while updating metadata: " . $e->getMessage());
      return [];
    } finally {
      $stmt->close();
    }
    return [$imageId];
  }
}
