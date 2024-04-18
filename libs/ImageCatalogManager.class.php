<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';

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
   * @return bool Liat of folders that were deleted successfully
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
        $file_path = strpos($row['image'], '://') === false ? '/p/' . $row['image'] : parse_url($row['image'], PHP_URL_PATH);
        $s3_file_path = ltrim($file_path, '/');

        if ($this->s3->getObjectMetadataFromR2($s3_file_path) !== false) {
          error_log("Deleting file from S3: " . $s3_file_path . PHP_EOL);

          try {
            $this->s3->deleteFromS3($s3_file_path);
            $filesToPurge[] = $row['image'];
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

    $folders = [];

    try {
      $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
      $stmt = $this->link->prepare("SELECT * FROM users_images_folders WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('s' . str_repeat('i', count($folderIds)),  $this->usernpub, ...$folderIds);
      $stmt->execute();
      $result = $stmt->get_result();
      $folders = $result->fetch_all(MYSQLI_ASSOC);
      $folderIds = array_column($folders, 'id');
      $stmt->close();
    } catch (Exception $e) {
      error_log("Error occurred while fetching folder records: " . $e->getMessage());
      return $folders;
    }

    if (!empty($folders)) {
      $folderNames = array_column($folders, 'folder');
      $placeholders = implode(',', array_fill(0, count($folderNames), '?'));

      try {
        // TODO: This is a temp hack to delete the folder while old folder structure is in place
        $stmt = $this->link->prepare("UPDATE users_images SET folder = NULL WHERE usernpub = ? AND folder IN ($placeholders)");
        $stmt->bind_param('s' . str_repeat('s', count($folderNames)), $this->usernpub, ...$folderNames);
        $stmt->execute();

        // TODO: Workaround, should be gone ASAP
        $stmt = $this->link->prepare("DELETE FROM users_images WHERE usernpub = ? AND folder IN ($placeholders) AND image = 'https://nostr.build/p/Folder.png'");
        $stmt->bind_param('s' . str_repeat('s', count($folderNames)), $this->usernpub, ...$folderNames);
        $stmt->execute();
      } catch (Exception $e) {
        error_log("Error occurred while updating and deleting folder records: " . $e->getMessage());
        return [];
      } finally {
        $stmt->close();
      }
    }

    try {
      $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
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
        $stmt = $this->link->prepare("UPDATE users_images SET folder = NULL, folder_id = NULL WHERE usernpub = ? AND id IN ($placeholders)");
        $stmt->bind_param('s' . str_repeat('i', count($imageIds)), $this->usernpub, ...$imageIds);
        if (!$stmt->execute()) {
          throw new Exception("Failed to move images");
        }
        return $imageIds;
      }
      // Get folder name from the folder ID
      $stmt = $this->link->prepare("SELECT folder FROM users_images_folders WHERE usernpub = ? AND id = ?");
      $stmt->bind_param('si', $this->usernpub, $folderId);
      if (!$stmt->execute()) {
        throw new Exception("Failed to fetch folder name");
      }
      $result = $stmt->get_result();
      $res = $result->fetch_assoc();
      $stmt->close();
      if (empty($res)) {
        throw new Exception("Folder not found");
      }
      // Get folder name into a variable
      $folder = $res['folder'];
      // Update the folder and folder ID for the images
      $stmt = $this->link->prepare("UPDATE users_images SET folder = ?, folder_id = ? WHERE usernpub = ? AND id IN ($placeholders)");
      $stmt->bind_param('sis' . str_repeat('i', count($imageIds)), $folder, $folderId, $this->usernpub, ...$imageIds);
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
}
