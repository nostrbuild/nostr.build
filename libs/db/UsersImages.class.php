<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class UsersImages extends DatabaseTable
{
  /**
   * Summary of __construct
   * @param mysqli $db
   */
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'users_images');
    $this->validationRules = [
      'id' => v::optional(v::intVal()),
      'usernpub' => v::notEmpty()->stringType()->length(1, 70),
      'image' => v::notEmpty()->stringType()->length(1, 255),
      'folder' => v::notEmpty()->stringType()->length(1, 255),
      'flag' => v::notEmpty()->stringType()->length(1, 10),
      'created_at' => v::optional(v::dateTime()),
      'file_size' => v::notEmpty()->intType(),
      'folder_id' => v::optional(v::intType()),
      'media_width' => v::optional(v::intType()->min(0)),
      'media_height' => v::optional(v::intType()->min(0)),
      'blurhash' => v::optional(v::stringType()->length(1, 255)),
      'mime_type' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  // Method to return total size of all images uploaded by a user
  /**
   * Summary of getTotalSize
   * @param string $usernpub
   * @return int|null
   */
  public function getTotalSize(string $usernpub): int | null
  {
    $sql = "
    SELECT
        COALESCE(SUM(COALESCE(ui.file_size, 0)), 0) AS totalSize,
        COUNT(ui.id) AS fileCount
    FROM (
        SELECT NULL AS id, 'TOTAL' AS folder
    ) AS dummy
    LEFT JOIN {$this->tableName} ui ON ui.usernpub = ?
        AND ui.image != 'https://nostr.build/p/Folder.png'
    GROUP BY dummy.id, dummy.folder
    ";
    // Prepare and execute the statement
    $totalSize = null;
    $totalCount = null;
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('s', $usernpub);
      $stmt->execute();
      $stmt->bind_result($totalSize, $totalCount);
      $stmt->fetch();
      $stmt->close();
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
    }
    // May return null if something fails
    return $totalSize;
  }

  // Method to return list of files uploaded by a specific user and in a specific folder
  /**
   * Summary of getFiles
   * @param string $npub
   * @param mixed $folderId
   * @return array
   */
  public function getFiles(string $npub, ?int $folderId = null): array
  {
    $sql = "
    SELECT
      ui.id,
      ui.image,
      ui.flag,
      ui.created_at,
      ui.file_size,
      ui.folder_id,
      ui.media_width,
      ui.media_height,
      ui.blurhash
    FROM {$this->tableName} ui
    WHERE ((? IS NULL AND ui.folder_id IS NULL) OR ui.folder_id = ?)
    AND ui.usernpub = ?
    ORDER BY ui.created_at DESC
    ";

    try {
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('iis', $folderId, $folderId, $npub);
      $stmt->execute();

      $result = $stmt->get_result(); // get mysqli_result object
      $files = $result->fetch_all(MYSQLI_ASSOC); // fetch all rows as an associative array

      $stmt->close();
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
      throw $e;
    }
    return $files;
  }
}
