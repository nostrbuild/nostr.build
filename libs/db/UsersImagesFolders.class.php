<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

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

  public function getFoldersStats(string $usernpub): array
  {
    // Populate information about folders and files
    $query = "
        SELECT
        uif.id,
        uif.folder,
        SUM(COALESCE(ui.file_size, 0)) AS totalSize,
        COUNT(ui.id) AS fileCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) = 'gif' THEN 1 ELSE 0 END), 0) AS gifCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) IN ('mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS avCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) NOT IN ('gif', 'mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS imageCount,
        COALESCE(SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END), 0) AS publicCount
    FROM users_images_folders uif
    LEFT JOIN users_images ui ON uif.id = ui.folder_id
        AND ui.image != 'https://nostr.build/p/Folder.png'
    WHERE uif.usernpub = ?
    GROUP BY uif.id, uif.folder
    UNION ALL
    SELECT
        NULL AS id,
        '/' AS folder,
        COALESCE(SUM(COALESCE(ui.file_size, 0)), 0) AS totalSize,
        COUNT(*) AS fileCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) = 'gif' THEN 1 ELSE 0 END), 0) AS gifCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) IN ('mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS avCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) NOT IN ('gif', 'mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS imageCount,
        COALESCE(SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END), 0) AS publicCount
    FROM users_images ui
    WHERE ui.usernpub = ?
        AND ui.folder_id IS NULL
        AND ui.image != 'https://nostr.build/p/Folder.png'
    UNION ALL
    SELECT
        dummy.id,
        dummy.folder,
        COALESCE(SUM(COALESCE(ui.file_size, 0)), 0) AS totalSize,
        COUNT(ui.id) AS fileCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) = 'gif' THEN 1 ELSE 0 END), 0) AS gifCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) IN ('mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS avCount,
        COALESCE(SUM(CASE WHEN SUBSTRING_INDEX(ui.image, '.', -1) NOT IN ('gif', 'mov', 'mp4', 'mp3') THEN 1 ELSE 0 END), 0) AS imageCount,
        COALESCE(SUM(CASE WHEN ui.flag = 1 THEN 1 ELSE 0 END), 0) AS publicCount
    FROM (
        SELECT NULL AS id, 'TOTAL' AS folder
    ) AS dummy
    LEFT JOIN users_images ui ON ui.usernpub = ?
        AND ui.image != 'https://nostr.build/p/Folder.png'
    GROUP BY dummy.id, dummy.folder
      ";

    $stmt = $this->db->prepare($query);
    $stmt->bind_param('sss', $usernpub, $usernpub, $usernpub);
    $stmt->execute();

    $result = $stmt->get_result();
    $account_folders_data = [];
    $folderCount = 0;

    while ($row = $result->fetch_assoc()) {
      if ($row['folder'] === 'TOTAL') {
        $account_folders_data['TOTAL'] = $row;
      } else {
        if ($row['folder'] !== '/') {
          $folderCount++;
        }
        $account_folders_data['FOLDERS'][$row['folder']] = $row;
      }
    }
    // add folderCount to the TOTAL
    $account_folders_data['TOTAL']['folderCount'] = $folderCount;
    $stmt->close();
    return $account_folders_data;
  }

  public function findFolderByNameOrCreate(string $folder_name): int
  {
    $this->db->begin_transaction();

    // First, try to select the folder
    $selectStmt = $this->db->prepare("SELECT id FROM {$this->tableName} WHERE folder = ?");
    $selectStmt->bind_param("s", $folder_name);
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
      $insertStmt = $this->db->prepare("INSERT INTO {$this->tableName} (folder) VALUES (?)");
      $insertStmt->bind_param("s", $folder_name);
      $insertStmt->execute();
      $insertStmt->close();

      $folderId = $this->db->insert_id;
    }

    // Commit the transaction
    $this->db->commit();

    // Return the ID
    return $folderId;
  }
}
