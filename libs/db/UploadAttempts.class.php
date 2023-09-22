<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class UploadAttempts extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'upload_attempts');
    $this->requiredValidationRules = [
      'filename' => v::notEmpty()->stringType()->length(null, 255),
    ];
    $this->optionalValidationRules = [
      'id' => v::intVal(),
      'usernpub' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  // Method that deviates from the parent class and table, but is needed to validate uploads
  public function recordUpload(string $filename, string $userNpub): void
  {
    $sql = "INSERT INTO " . $this->tableName . " (filename, usernpub, attempt_timestamp)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE attempt_timestamp = NOW()";
    try {
      $bindFileName = $filename;
      $bindUserNpub = $userNpub;
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('ss', $bindFileName, $bindUserNpub);
      $stmt->execute();
      $stmt->close();
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    }
  }
}
