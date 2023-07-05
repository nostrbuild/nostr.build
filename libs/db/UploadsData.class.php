<?php

/**
 * Sample usage:
 *
 *try {
 *    $uploadsData->beginTransaction();
 *    
 *    $uploadsData->insert([
 *        'filename' => 'test1.jpg',
 *        'approval_status' => 'approved',
 *        'metadata' => ['resolution' => '1920x1080'],
 *        'file_size' => 1024,
 *        'type' => 'picture'
 *    ]);
 *    
 *    $uploadsData->insert([
 *        'filename' => 'test2.jpg',
 *        'approval_status' => 'approved',
 *        'metadata' => ['resolution' => '1920x1080'],
 *        'file_size' => 1024,
 *        'type' => 'picture'
 *    ]);
 *    
 *    $uploadsData->commit();
 *} catch (Exception $e) {
 *    $uploadsData->rollback();
 *    // handle error
 *}
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class UploadsData extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'uploads_data');
    $this->requiredValidationRules = [
      'filename' => v::notEmpty()->stringType()->length(null, 255),
    ];
    $this->optionalValidationRules = [
      'id' => v::intVal(),
      'approval_status' => v::in(['approved', 'pending', 'rejected', 'adult']),
      'upload_date' => v::dateTime(),
      'metadata' => v::json(),
      'file_size' => v::numericVal(),
      'type' => v::in(['picture', 'video', 'unknown', 'profile']),
      'media_width' => v::optional(v::intType()->min(0)),
      'media_height' => v::optional(v::intType()->min(0)),
      'blurhash' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  public function getStats(): array
  {
    $result = [
      'total_files' => 0,
      'total_size' => 0,
    ];
    // Fetch statistics
    $sql = "SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM {$this->tableName}";
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $stmt->bind_result($result['total_files'], $result['total_size']);
      $stmt->fetch();
      $stmt->close();
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    }
    return $result;
  }

  // Method that deviates from the parent class and table, but is needed to validate uploads
  public function checkRejected($filehash): bool
  {
    $sql = "SELECT id FROM rejected_files WHERE filename LIKE ?";
    $rejected = false;
    try {
      $bindFileHash = $filehash . '%';
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('s', $bindFileHash);
      $stmt->execute();
      $stmt->store_result(); // This is required before you can call mysqli_stmt_num_rows()

      if ($stmt->num_rows > 0) {
        $rejected = true;
      }

      $stmt->free_result();
      $stmt->close();
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    }
    return $rejected;
  }

  public function getUploadData($filehash)
  {
    $sql = "SELECT * FROM {$this->tableName} WHERE filename LIKE ?";
    try {
      $bindFileHash = $filehash . '%';
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('s', $bindFileHash);
      $stmt->execute();

      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
        // Fetch associative array and return it
        $data = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        return $data;
      } else {
        // No matching record found
        $stmt->close();
        return false;
      }
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
      return false;
    }
  }
}
