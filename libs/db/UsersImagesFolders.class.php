<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;
/*
desc users_images_folders;
+------------+--------------+------+-----+-------------------+-------------------+
| Field      | Type         | Null | Key | Default           | Extra             |
+------------+--------------+------+-----+-------------------+-------------------+
| id         | int          | NO   | PRI | NULL              | auto_increment    |
| usernpub   | varchar(70)  | NO   | MUL | NULL              |                   |
| folder     | varchar(255) | NO   |     | NULL              |                   |
| created_at | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| parent_id  | int          | YES  | MUL | NULL              |                   |
+------------+--------------+------+-----+-------------------+-------------------+
*/

class UsersImagesFolders extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'users_images_folders');
    $this->validationRules = [
      'id' => v::optional(v::intVal()),
      'usernpub' => v::notEmpty()->stringType()->length(1, 70),
      'folder' => v::notEmpty()->stringType()->length(1, 255),
      'created_at' => v::optional(v::dateTime()),
    ];
  }

  /**
   * Summary of getFolders
   * @param string $usernpub
   * @return array
   */
  public function getFolders(string $usernpub): array
  {
    $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} WHERE usernpub = ?");
    $stmt->bind_param("s", $usernpub);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
  }

  public function getFoldersWithStats(string $usernpub): array
  {
    $stmt = $this->db->prepare("
    SELECT
        COALESCE(uif.folder, '') AS folder,
        COALESCE(dt.id, uif.id, 0) AS id,
        COALESCE(dt.totalSize, 0) AS allSize,
        COALESCE(dt.fileCount, 0) AS \"all\",
        COALESCE(dt.imageSize, 0) AS imageSize,
        COALESCE(dt.imageCount, 0) AS images,
        COALESCE(dt.gifSize, 0) AS gifSize,
        COALESCE(dt.gifCount, 0) AS gifs,
        COALESCE(dt.videoSize, 0) AS videoSize,
        COALESCE(dt.videoCount, 0) AS videos,
        COALESCE(dt.audioSize, 0) AS audioSize,
        COALESCE(dt.audioCount, 0) AS audio,
        COALESCE(dt.documentSize, 0) AS documentSize,
        COALESCE(dt.documentCount, 0) AS documents,
        COALESCE(dt.archiveSize, 0) AS archiveSize,
        COALESCE(dt.archiveCount, 0) AS archives,
        COALESCE(dt.otherSize, 0) AS otherSize,
        COALESCE(dt.otherCount, 0) AS others,
        COALESCE(dt.publicCount, 0) AS publicCount
    FROM users_images_folders uif
    LEFT JOIN (
        SELECT
            ui.folder_id,
            ui.folder_id AS id,
            SUM(COALESCE(ui.file_size, 0)) AS totalSize,
            COUNT(ui.id) AS fileCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' AND ui.mime_type != 'image/svg+xml' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS imageSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' AND ui.mime_type != 'image/svg+xml' THEN 1 ELSE 0 END) AS imageCount,
            SUM(CASE WHEN ui.mime_type = 'image/gif' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS gifSize,
            SUM(CASE WHEN ui.mime_type = 'image/gif' THEN 1 ELSE 0 END) AS gifCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS videoSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN 1 ELSE 0 END) AS videoCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS audioSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN 1 ELSE 0 END) AS audioCount,
            SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS documentSize,
            SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN 1 ELSE 0 END) AS documentCount,
            SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS archiveSize,
            SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN 1 ELSE 0 END) AS archiveCount,
            SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS otherSize,
            SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN 1 ELSE 0 END) AS otherCount,
            SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END) AS publicCount
        FROM users_images ui
        WHERE ui.usernpub = ?
        GROUP BY ui.folder_id
    ) dt ON uif.id = dt.folder_id
    WHERE uif.usernpub = ?
    UNION ALL
    SELECT
        'Home: Main Folder' AS folder,
        COALESCE(dt.id, 0) AS id,
        COALESCE(dt.totalSize, 0) AS allSize,
        COALESCE(dt.fileCount, 0) AS \"all\",
        COALESCE(dt.imageSize, 0) AS imageSize,
        COALESCE(dt.imageCount, 0) AS images,
        COALESCE(dt.gifSize, 0) AS gifSize,
        COALESCE(dt.gifCount, 0) AS gifs,
        COALESCE(dt.videoSize, 0) AS videoSize,
        COALESCE(dt.videoCount, 0) AS videos,
        COALESCE(dt.audioSize, 0) AS audioSize,
        COALESCE(dt.audioCount, 0) AS audio,
        COALESCE(dt.documentSize, 0) AS documentSize,
        COALESCE(dt.documentCount, 0) AS documents,
        COALESCE(dt.archiveSize, 0) AS archiveSize,
        COALESCE(dt.archiveCount, 0) AS archives,
        COALESCE(dt.otherSize, 0) AS otherSize,
        COALESCE(dt.otherCount, 0) AS others,
        COALESCE(dt.publicCount, 0) AS publicCount
    FROM (
        SELECT
            0 AS id,
            SUM(COALESCE(ui.file_size, 0)) AS totalSize,
            COUNT(ui.id) AS fileCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' AND ui.mime_type != 'image/svg+xml' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS imageSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' AND ui.mime_type != 'image/svg+xml' THEN 1 ELSE 0 END) AS imageCount,
            SUM(CASE WHEN ui.mime_type = 'image/gif' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS gifSize,
            SUM(CASE WHEN ui.mime_type = 'image/gif' THEN 1 ELSE 0 END) AS gifCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS videoSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN 1 ELSE 0 END) AS videoCount,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS audioSize,
            SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN 1 ELSE 0 END) AS audioCount,
            SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS documentSize,
            SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN 1 ELSE 0 END) AS documentCount,
            SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS archiveSize,
            SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN 1 ELSE 0 END) AS archiveCount,
            SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS otherSize,
            SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN 1 ELSE 0 END) AS otherCount,
            SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END) AS publicCount
        FROM users_images ui
        WHERE ui.usernpub = ?
            AND ui.folder_id IS NULL
    ) dt
    ");
    $stmt->bind_param("sss", $usernpub, $usernpub, $usernpub);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
  }

  public function getTotalStats(string $usernpub): array
  {
    $stmt = $this->db->prepare("
    SELECT
        'TOTAL' AS folder,
        0 AS id,
        SUM(COALESCE(ui.file_size, 0)) AS allSize,
        COUNT(ui.id) AS \"all\",
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS imageSize,
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'image/' AND ui.mime_type != 'image/gif' THEN 1 ELSE 0 END) AS images,
        SUM(CASE WHEN ui.mime_type = 'image/gif' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS gifSize,
        SUM(CASE WHEN ui.mime_type = 'image/gif' THEN 1 ELSE 0 END) AS gifs,
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS videoSize,
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'video/' THEN 1 ELSE 0 END) AS videos,
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS audioSize,
        SUM(CASE WHEN SUBSTR(ui.mime_type, 1, 6) = 'audio/' THEN 1 ELSE 0 END) AS audio,
        SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS documentSize,
        SUM(CASE WHEN ui.mime_type = 'application/pdf' THEN 1 ELSE 0 END) AS documentCount,
        SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS archiveSize,
        SUM(CASE WHEN ui.mime_type IN ('application/zip', 'application/x-tar') THEN 1 ELSE 0 END) AS archiveCount,
        SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN COALESCE(ui.file_size, 0) ELSE 0 END) AS otherSize,
        SUM(CASE WHEN ui.mime_type = 'image/svg+xml' THEN 1 ELSE 0 END) AS otherCount,
        SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END) AS publicCount
    FROM users_images ui
    WHERE ui.usernpub = ?
    ");
    $stmt->bind_param("s", $usernpub);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
  }

  public function findFolderByNameOrCreate(string $usernpub, string $folder_name, int $parent_id = null): int
  {
    if (empty($folder_name)) {
      throw new Exception('Folder name cannot be empty');
    }
    $this->db->begin_transaction();

    // First, try to select the folder
    if ($parent_id) {
      $sql = "SELECT id FROM {$this->tableName} WHERE folder = ? AND usernpub = ? AND parent_id = ?";
      $selectStmt = $this->db->prepare($sql);
      $selectStmt->bind_param("ssi", $folder_name, $usernpub, $parent_id);
    } else {
      $sql = "SELECT id FROM {$this->tableName} WHERE folder = ? AND usernpub = ?";
      $selectStmt = $this->db->prepare($sql);
      $selectStmt->bind_param("ss", $folder_name, $usernpub);
    }
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $data = $result->fetch_assoc();
    $selectStmt->close();

    $folderId = null;

    // If the folder exists, use its id
    if ($data) {
      $folderId = $data['id'];
    } else {
      // If the folder doesn't exist, insert it and get the last inserted id
      if ($parent_id) {
        $insertStmt = $this->db->prepare("INSERT INTO {$this->tableName} (folder, usernpub, parent_id) VALUES (?,?,?)");
        $insertStmt->bind_param("ssi", $folder_name, $usernpub, $parent_id);
      } else {
        $insertStmt = $this->db->prepare("INSERT INTO {$this->tableName} (folder, usernpub) VALUES (?,?)");
        $insertStmt->bind_param("ss", $folder_name, $usernpub);
      }
      $insertStmt->execute();
      $insertStmt->close();

      $folderId = $this->db->insert_id;
    }

    // Commit the transaction
    $this->db->commit();

    // Return the ID
    return $folderId;
  }

  public function findFolderByNameOrCreateHierarchy(string $usernpub, array $folderList): int
  {
    $folderId = null;
    $folderName = null;

    foreach ($folderList as $folder) {
      $folderName = $folder;
      $folderId = $this->findFolderByNameOrCreate($usernpub, $folderName, $folderId);
    }

    // Return the ID of the last folder
    return $folderId;
  }
}
