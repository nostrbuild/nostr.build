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
      'sha256_hash' => v::optional(v::stringType()->length(1, 255)),
      'title' => v::optional(v::stringType()->length(1, 255)),
      'ai_prompt' => v::optional(v::stringType()->length(1, 4096)),
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
  public function getFiles(string $npub, ?int $folderId = null, ?int $start = null, ?int $limit = null): array
  {
    $sql_nostr = "
    SELECT 
        ui.*,
        GROUP_CONCAT(CONCAT(uni.note_id, ':', UNIX_TIMESTAMP(unn.created_at))) AS associated_notes
    FROM 
        {$this->tableName} ui
        LEFT JOIN users_nostr_images uni ON ui.id = uni.image_id
        LEFT JOIN users_nostr_notes unn ON uni.note_id = unn.note_id
    WHERE 
        ((? IS NULL AND ui.folder_id IS NULL) OR ui.folder_id = ?)
        AND ui.usernpub = ?
        AND ui.image != 'https://nostr.build/p/Folder.png'
    GROUP BY
        ui.id
    ORDER BY 
        ui.created_at DESC
    ";
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
      ui.blurhash,
      ui.mime_type,
      ui.sha256_hash,
      ui.title,
      ui.ai_prompt
    FROM {$this->tableName} ui
    WHERE ((? IS NULL AND ui.folder_id IS NULL) OR ui.folder_id = ?)
    AND ui.usernpub = ?
    AND ui.image != 'https://nostr.build/p/Folder.png'
    ORDER BY ui.created_at DESC
    ";
    // Decide the SQL based on presence of start and limit
    if ($start !== null && $limit !== null) {
      $sql_nostr .= "LIMIT ?, ?";
    }

    try {
      $stmt = $this->db->prepare($sql_nostr);
      // start and limit are optional
      if ($start !== null && $limit !== null) {
        $stmt->bind_param('iisii', $folderId, $folderId, $npub, $start, $limit);
      } else {
        $stmt->bind_param('iis', $folderId, $folderId, $npub);
      }
      $stmt->execute();

      $result = $stmt->get_result(); // get mysqli_result object
      if ($result === false) {
        error_log("Error: " . $this->db->error);
        throw new Exception("Error: " . $this->db->error);
      }
      $files = $result->fetch_all(MYSQLI_ASSOC); // fetch all rows as an associative array

      $stmt->close();
      return $files;
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
      throw $e;
    }
  }
}
