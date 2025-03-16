<?php
require_once __DIR__ . '/CloudflarePurge.class.php';
require_once __DIR__ . '/ImageCatalogManager.class.php';
require_once __DIR__ . '/BlossomFrontEndAPI.class.php';

class DeleteMedia
{
  private $userNpub;
  private $mediaName;
  private $db;
  private $s3;
  private $CFClient;
  private $imageCatalogManager;
  private $blossomFrontendCall = false;
  private $blossomFrontEndAPI;

  function __construct(string $userNpub, string $mediaName, mysqli $db, mixed $s3)
  {
    $this->userNpub = $userNpub;
    // Strip extension from media name and escape % and _ characters
    $this->mediaName = str_replace(['%', '_'], ['\%', '\_'], pathinfo($mediaName, PATHINFO_FILENAME));
    if (empty($this->mediaName)) {
      throw new Exception('Invalid media name', 400);
    }
    $this->db = $db;
    $this->s3 = $s3;
    $this->CFClient = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
    $this->imageCatalogManager = new ImageCatalogManager($db, $s3, $userNpub);
    $this->blossomFrontEndAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
  }

  function deleteMedia()
  {
    if ($this->isHexHash()) {
      return $this->deleteFreeMedia();
    } else {
      return $this->deletePaidMedia();
    }
  }

  function deleteBlossomMediaAPIMethod(): bool
  {
    if (!$this->isHexHash()) {
      throw new Exception('Invalid media name', 400);
    }
    $this->blossomFrontendCall = true;
    // Reinitialize image catalog manager with blossom frontend call flag
    $this->imageCatalogManager = new ImageCatalogManager($this->db, $this->s3, $this->userNpub, true);
    // Unfortunatelly, it is possible for the same hash under the same owner to be in both paid and free uploads
    // Thanks, Obama!
    $free = false;
    $paid = false;
    try {
      error_log('Deleting free media');
      $free = $this->deleteFreeMedia();
    } catch (Exception $e) {
      error_log($e->getMessage());
      if ($e->getCode() !== 404) {
        throw $e;
      }
    }
    try {
      error_log('Deleting paid media');
      $paid = $this->deletePaidMedia();
    } catch (Exception $e) {
      error_log($e->getMessage());
      if ($e->getCode() !== 404) {
        throw $e;
      }
    }
    // Either free or paid, return true
    return $free || $paid;
  }

  function isHexHash()
  {
    // If file name is sha256 hash, it is a free upload
    return preg_match('/^[0-9a-f]{64}/', $this->mediaName);
  }


  function deleteFreeMedia(): bool
  {
    $this->db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

    try {
      $assocIds = $this->getUploadAttemptIds();
      if (empty($assocIds)) {
        throw new Exception('Media not found', 404);
      }
      error_log('Media found: ' . json_encode($assocIds) . PHP_EOL);

      $mediaData = $this->blossomFrontendCall ? $this->getBlossomMediaData() : $this->getMediaData();
      error_log('Media data: ' . json_encode($mediaData) . PHP_EOL);
      if ($mediaData === null) {
        $this->deleteUploadAttempts($assocIds);
        $this->db->commit();
        $blossomHash = $this->getBlossomMediahash();
        if (!$this->blossomFrontendCall && !empty($blossomHash)) {
          $this->blossomFrontEndAPI->deleteMedia($this->userNpub, $blossomHash);
        }
        return true;
      }

      $this->processMediaDeletion($mediaData);
      $this->deleteUploadAttempts($assocIds);
      $this->db->commit();
    } catch (Exception $e) {
      $this->db->rollback();
      error_log($e->getMessage());
      // If 404, rethrow exception to return 404 status
      if ($e->getCode() === 404) {
        throw $e;
      }
      return false;
    }

    return true;
  }

  private function getUploadAttemptIds(): array
  {
    $stmt = $this->db->prepare("SELECT id FROM upload_attempts WHERE usernpub = ? AND filename LIKE ? ESCAPE '\\\\'");
    $filename = $this->mediaName . '%';
    $stmt->bind_param('ss', $this->userNpub, $filename);
    $stmt->execute();
    $result = $stmt->get_result();
    $assocIds = [];
    while ($row = $result->fetch_assoc()) {
      $assocIds[] = $row['id'];
    }
    $stmt->close();

    return $assocIds;
  }

  private function getMediaData(): ?array
  {
    $stmt = $this->db->prepare("SELECT * FROM uploads_data WHERE usernpub = ? AND filename LIKE ? ESCAPE '\\\\' LIMIT 1");
    $filename = $this->mediaName . '%';
    $stmt->bind_param('ss', $this->userNpub, $filename);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
  }

  private function getBlossomMediaData(): ?array
  {
    $stmt = $this->db->prepare("SELECT * FROM uploads_data WHERE usernpub = ? AND blossom_hash = ? LIMIT 1");
    $stmt->bind_param('ss', $this->userNpub, $this->mediaName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row;
  }

  private function getBlossomMediahash(): ?string
  {
    // We allow to get hash of the media regardless of the owner
    $stmt = $this->db->prepare("SELECT blossom_hash FROM uploads_data WHERE filename LIKE ? ESCAPE '\\\\' LIMIT 1");
    $filename = $this->mediaName . '%';
    $stmt->bind_param('s', $filename);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row['blossom_hash'] ?? null;
  }

  private function deleteUploadAttempts(array $ids): void
  {
    $stmt = $this->db->prepare('DELETE FROM upload_attempts WHERE id = ?');
    foreach ($ids as $id) {
      $stmt->bind_param('i', $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  private function processMediaDeletion(array $mediaData): void
  {
    $uploadId = $mediaData['id'];
    $mediaType = $mediaData['type'];
    $mediaMimeType = $mediaData['mime'] ?? null;
    $blossomHash = $mediaData['blossom_hash'] ?? null;

    $objectKey = $this->getObjectKey($mediaData['filename'], $mediaType);

    $currentSha256 = $this->s3->getS3ObjectHash(objectKey: $objectKey, paidAccount: false, mimeType: $mediaMimeType);
    $this->s3->deleteFromS3(objectKey: $objectKey, paidAccount: false, mimeType: $mediaMimeType);
    $purgeFilename = !empty($currentSha256) ? "{$mediaData['filename']}|{$currentSha256}" : $mediaData['filename'];
    error_log('Purging: ' . $purgeFilename);
    $this->CFClient->purgeFiles([$purgeFilename]);
    // Blossom
    if (!$this->blossomFrontendCall && !empty($blossomHash)) {
      $this->blossomFrontEndAPI->deleteMedia($this->userNpub, $blossomHash);
    }

    $this->deleteFromUploadsData($uploadId);
  }

  private function getObjectKey(string $filename, string $mediaType): string
  {
    return match ($mediaType) {
      'picture' => 'i/' . $filename,
      'video' => 'av/' . $filename,
      'profile' => 'i/p/' . $filename,
      default => 'i/' . $filename,
    };
  }

  private function deleteFromUploadsData(int $id): void
  {
    $stmt = $this->db->prepare('DELETE FROM uploads_data WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
  }

  function deletePaidMedia(): bool
  {
    try {
      if ($this->blossomFrontendCall) {
        $image = $this->imageCatalogManager->getImageByBlossomHash($this->mediaName);
      } else {
        $image = $this->imageCatalogManager->getImageByName($this->mediaName);
      }
      if ($image === null) {
        throw new Exception('Image not found', 404);
      }
      $imageId = $image['id'];
      $deletedImageIds = $this->imageCatalogManager->deleteImages([$imageId]);
      // Compare deleted image ids with the image id to check if the image was deleted
      if (!in_array($imageId, $deletedImageIds)) {
        throw new Exception('Image was not deleted', 500);
      }
    } catch (Exception $e) {
      if ($e->getCode() === 404) {
        // Rethrow exception if the image was not found
        throw $e;
      }
      error_log($e->getMessage());
      return false;
    }
    return true;
  }
}
