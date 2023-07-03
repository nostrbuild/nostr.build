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

  public function getFoldersByUsernpub(string $usernpub): array
  {
    $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} WHERE usernpub = ?");
    $stmt->bind_param("s", $usernpub);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
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
