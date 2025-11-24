<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class RejectedFilesTable extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'rejected_files');
    $this->requiredValidationRules = [
      'filename' => v::notEmpty()->stringType()->length(null, 255),
    ];
    $this->optionalValidationRules = [
      'id' => v::intVal(),
      'filename' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  // Method that deviates from the parent class and table, but is needed to validate uploads
  public function getList(int $id = 0, int $limit = 100): array
  {
    $sql = "SELECT id, filename, type FROM " . $this->tableName . " WHERE id > ? ORDER BY id ASC LIMIT ?";
    try {
      $bindId = $id;
      $bindLimit = $limit;

      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('ii', $bindId, $bindLimit);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result === false) {
        throw new Exception("Error: " . $this->db->error);
      }

      $blacklistEntries = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      return $blacklistEntries;
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    }
    return [];
  }
}
