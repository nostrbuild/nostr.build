<?php

/**
 *  This is a base class for all database tables.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class DatabaseTable
{
  protected $db;
  protected $tableName;
  protected $validationRules = [];
  protected $requiredValidationRules = [];
  protected $optionalValidationRules = [];

  public function __construct(mysqli $db, $tableName)
  {
    $this->db = $db;
    $this->tableName = $tableName;
  }

  protected function getType($var)
  {
    switch (gettype($var)) {
      case 'double':
        return 'd';
      case 'integer':
        return 'i';
      default:
        return 's';
    }
  }

  protected function validate(array $data)
  {
    // TODO: Need to fix so it actually works correctly
    return;
    /*
    // Handle required fields
    $requiredValidator = v::keySet(...array_map(function ($key, $rule) {
      return v::key($key, $rule);
    }, array_keys($this->requiredValidationRules), $this->requiredValidationRules));

    // Validate required fields
    $requiredValidator->assert($data);

    // Validate each optional field
    foreach ($this->optionalValidationRules as $key => $rule) {
      if (array_key_exists($key, $data)) {
        $validator = v::key($key, $rule);
        $validator->assert($data);
      }
    }
    */
  }

  public function insert(array $data, $commit = true)
  {
    $this->validate($data);
    $placeholders = str_repeat('?,', count($data) - 1) . '?';

    $types = '';
    foreach ($data as $value) {
      $types .= $this->getType($value);
    }

    //$sql = "INSERT INTO {$this->tableName} (" . implode(',', array_keys($data)) . ") VALUES ($placeholders)";
    $insertPart = "INSERT INTO {$this->tableName} (" . implode(',', array_keys($data)) . ") VALUES ($placeholders)";

    // Construct update part of the query
    $updatePart = " ON DUPLICATE KEY UPDATE ";
    $updateClauses = [];
    foreach ($data as $key => $value) {
      $updateClauses[] = "$key = VALUES($key)";
    }
    $updatePart .= implode(', ', $updateClauses);

    // Put them together
    $sql = $insertPart . $updatePart;

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Prepare failed: (" . $this->db->errno . ") " . $this->db->error);
    }

    $dataValues = array_values($data);
    if (!$stmt->bind_param($types, ...$dataValues)) {
      throw new Exception("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if (!$stmt->execute()) {
      throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if ($commit) {
      $this->db->commit();
    }

    return $this->db->insert_id;
  }

  public function delete($id)
  {
    $sql = "DELETE FROM {$this->tableName} WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Prepare failed: (" . $this->db->errno . ") " . $this->db->error);
    }

    if (!$stmt->bind_param('i', $id)) {
      throw new Exception("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if (!$stmt->execute()) {
      throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if ($stmt->affected_rows == 0) {
      throw new Exception("No row with ID: " . $id . " found to delete.");
    }

    return true;
  }

  public function update($id, $data, $commit = true)
  {
    $columns = array_keys($data);
    $values = array_values($data);

    $types = '';
    foreach ($data as $value) {
      $types .= $this->getType($value);
    }

    $placeholders = implode(', ', array_map(function ($column) {
      return "$column = ?";
    }, $columns));

    $sql = "UPDATE {$this->tableName} SET $placeholders WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Prepare failed: (" . $this->db->errno . ") " . $this->db->error);
    }

    $values[] = $id; // append $id to the end of $values array
    $types .= 'i'; // append 'i' to the end of $types string

    if (!$stmt->bind_param($types, ...$values)) {
      throw new Exception("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if (!$stmt->execute()) {
      throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if ($commit) {
      $this->db->commit();
    }
  }

  public function get($id)
  {
    $sql = "SELECT * FROM {$this->tableName} WHERE id = ?";

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Prepare failed: (" . $this->db->errno . ") " . $this->db->error);
    }

    if (!$stmt->bind_param('i', $id)) {
      throw new Exception("Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    if (!$stmt->execute()) {
      throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if (!$data) {
      throw new Exception("No result found for ID: " . $id);
    }

    return $data;
  }

  public function beginTransaction()
  {
    $this->db->begin_transaction();
  }

  public function commit()
  {
    $this->db->commit();
  }

  public function rollback()
  {
    $this->db->rollback();
  }
}
