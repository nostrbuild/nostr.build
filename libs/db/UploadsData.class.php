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
      'usernpub' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  public function getStats(): array
  {
    $cacheKey = 'uploads_data_stats';
    $cacheTTL = 60; // Time-to-live in seconds

    // Try to get data from APCu cache first
    // if (apcu_exists($cacheKey)) {
    //   return apcu_fetch($cacheKey);
    // }

    $result = [
      'total_files' => 0,
      'total_size' => 0,
    ];

    // Fetch statistics from the database
    //$sql = "SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM {$this->tableName}";
    // Move toward summary table instead, to avoid undue load on the database
    $sql = "SELECT 
                (us.total_files + IFNULL(ui.count, 0)) AS total_files,
                (us.total_size + IFNULL(ui.total_image_size, 0)) AS total_size
            FROM 
                uploads_summary AS us
            LEFT JOIN 
                (SELECT 
                    COUNT(*) AS count, 
                    SUM(file_size) AS total_image_size 
                FROM 
                    users_images) AS ui
            ON 
                us.id = 1
            WHERE 
                us.id = 1;
            ";
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $stmt->bind_result($result['total_files'], $result['total_size']);
      $stmt->fetch();
      $stmt->close();

      // Save the database result to APCu cache
      // apcu_store($cacheKey, $result, $cacheTTL);
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

  public function checkBlacklisted(string $npub = null): bool
  {
    $sql = "SELECT id FROM blacklist WHERE (npub = ? OR ip = ?) LIMIT 1";
    $blacklisted = false;
    if (empty($npub)) {
      return $blacklisted;
    }
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('ss', $npub, $ip);
      $stmt->execute();
      $stmt->store_result(); // This is required before you can call mysqli_stmt_num_rows()

      if ($stmt->num_rows > 0) {
        $blacklisted = true;
        error_log("Blacklisted: " . $npub . " " . $ip);
      }
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    } finally {
      $stmt->free_result();
      $stmt->close();
    }
    return $blacklisted;
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
