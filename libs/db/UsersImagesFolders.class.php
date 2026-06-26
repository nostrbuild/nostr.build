<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
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
  public function getFolders(string $owner): array
  {
    $userUuid = resolveOwnerUuid($this->db, $owner);
    $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} WHERE user_uuid = ?");
    $stmt->bind_param("s", $userUuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
  }

  public function getFoldersWithStats(string $owner): array
  {
    $userUuid = resolveOwnerUuid($this->db, $owner);
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
        WHERE ui.user_uuid = ?
        GROUP BY ui.folder_id
    ) dt ON uif.id = dt.folder_id
    WHERE uif.user_uuid = ?
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
        WHERE ui.user_uuid = ?
            AND ui.folder_id IS NULL
    ) dt
    ");
    $stmt->bind_param("sss", $userUuid, $userUuid, $userUuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_all(MYSQLI_ASSOC);
  }

  public function getTotalStats(string $owner): array
  {
    $userUuid = resolveOwnerUuid($this->db, $owner);
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
    WHERE ui.user_uuid = ?
    ");
    $stmt->bind_param("s", $userUuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
  }

  public function findFolderByNameOrCreate(string $owner, string $folder_name, ?int $parent_id = null): int
  {
    if (empty($folder_name)) {
      throw new Exception('Folder name cannot be empty');
    }
    // Look up by the stable uuid; keep the npub for the NOT NULL usernpub column.
    // New writes populate BOTH columns explicitly; the trigger backstops any
    // legacy caller that still inserts only usernpub.
    $userUuid = resolveOwnerUuid($this->db, $owner);
    $usernpub = str_starts_with($owner, 'npub1') ? $owner : (uuidToNpub($this->db, $owner) ?? '');
    // First, try to select the folder
    if ($parent_id) {
      $sql = "SELECT id FROM {$this->tableName} WHERE folder = ? AND user_uuid = ? AND parent_id = ?";
      $selectStmt = $this->db->prepare($sql);
      $selectStmt->bind_param("ssi", $folder_name, $userUuid, $parent_id);
    } else {
      $sql = "SELECT id FROM {$this->tableName} WHERE folder = ? AND user_uuid = ?";
      $selectStmt = $this->db->prepare($sql);
      $selectStmt->bind_param("ss", $folder_name, $userUuid);
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
      // Insert it. With the UNIQUE(usernpub, folder) constraint a concurrent
      // request (or a same-named sibling) can race us between the SELECT above and
      // this INSERT; on a duplicate key (1062), fall back to the existing row's id
      // instead of throwing a 500.
      if ($parent_id) {
        $insertStmt = $this->db->prepare("INSERT INTO {$this->tableName} (folder, usernpub, user_uuid, parent_id) VALUES (?,?,?,?)");
        $insertStmt->bind_param("sssi", $folder_name, $usernpub, $userUuid, $parent_id);
      } else {
        $insertStmt = $this->db->prepare("INSERT INTO {$this->tableName} (folder, usernpub, user_uuid) VALUES (?,?,?)");
        $insertStmt->bind_param("sss", $folder_name, $usernpub, $userUuid);
      }
      try {
        $insertStmt->execute();
        $folderId = $this->db->insert_id;
      } catch (\mysqli_sql_exception $e) {
        if ((int) $this->db->errno !== 1062) {
          throw $e;
        }
        // Duplicate key — another writer won the race, or the name already exists
        // within the UNIQUE(user_uuid, folder) scope. Return that existing id.
        $reStmt = $this->db->prepare("SELECT id FROM {$this->tableName} WHERE folder = ? AND user_uuid = ? LIMIT 1");
        $reStmt->bind_param("ss", $folder_name, $userUuid);
        $reStmt->execute();
        $existing = $reStmt->get_result()->fetch_assoc();
        $reStmt->close();
        if (!$existing) {
          throw $e;
        }
        $folderId = (int) $existing['id'];
      } finally {
        $insertStmt->close();
      }
    }

    // Return the ID
    return $folderId;
  }

  public function findFolderByNameOrCreateHierarchy(string $owner, array $folderList): int
  {
    $folderId = null;
    $folderName = null;

    foreach ($folderList as $folder) {
      $folderName = $folder;
      $folderId = $this->findFolderByNameOrCreate($owner, $folderName, $folderId);
    }

    // Return the ID of the last folder
    return $folderId;
  }

  public function getFolderNameById(string $owner, ?int $folderId): string
  {
    if ($folderId === null) {
      return 'Home: Main Folder';
    }
    $userUuid = resolveOwnerUuid($this->db, $owner);
    $stmt = $this->db->prepare("SELECT folder FROM {$this->tableName} WHERE user_uuid = ? AND id = ?");
    $stmt->bind_param("si", $userUuid, $folderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data ? $data['folder'] : 'Unknown Folder';
  }
}
