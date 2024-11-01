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
      'description' => v::optional(v::stringType()->length(1, 4096)),
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
  public function getFiles(string $npub, ?int $folderId = null, ?int $start = null, ?int $limit = null, ?string $filter = null): array
  {
    $filterConditions = [
      'all' => '',
      'images' => "AND (ui.mime_type LIKE 'image%' AND ui.mime_type != 'image/gif') AND ui.mime_type != 'image/svg+xml'",
      'videos' => "AND ui.mime_type LIKE 'video%'",
      'audio' => "AND ui.mime_type LIKE 'audio%'",
      'gifs' => "AND ui.mime_type = 'image/gif'",
      'documents' => "AND ui.mime_type = 'application/pdf'",
      'archives' => "AND ui.mime_type IN ('application/x-tar', 'application/zip')",
      'others' => "AND ui.mime_type IN ('image/svg+xml')",
    ];

    $filter_sql = $filterConditions[$filter] ?? '';
    $folder_id_sql = $folderId !== null ? "folder_id = ?" : "folder_id IS NULL";

    $sql_nostr = "
          SELECT 
              ui.*,
              (SELECT GROUP_CONCAT(CONCAT(uni.note_id, ':', UNIX_TIMESTAMP(unn.created_at)))
               FROM users_nostr_images uni
               LEFT JOIN users_nostr_notes unn ON uni.note_id = unn.note_id
               WHERE uni.image_id = ui.id) AS associated_notes
          FROM {$this->tableName} ui
          WHERE {$folder_id_sql}
            AND usernpub = ?
            {$filter_sql}
          ORDER BY created_at DESC
      ";

    if ($start !== null && $limit !== null) {
      $sql_nostr .= "LIMIT ?, ?";
    }

    $params = [];
    $types = '';

    if ($folderId !== null) {
      $params[] = $folderId;
      $types .= 'i';
    }

    $params[] = $npub;
    $types .= 's';

    if ($start !== null && $limit !== null) {
      $params[] = $start;
      $params[] = $limit;
      $types .= 'ii';
    }

    try {
      $stmt = $this->db->prepare($sql_nostr);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result === false) {
        throw new Exception("Error: " . $this->db->error);
      }

      $files = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      return $files;
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
      throw $e;
    }
  }

  public function getFile(string $npub, int $fileId): array
  {
    $sql = "
    SELECT 
        ui.*,
        (SELECT GROUP_CONCAT(CONCAT(uni.note_id, ':', UNIX_TIMESTAMP(unn.created_at)))
         FROM users_nostr_images uni
         LEFT JOIN users_nostr_notes unn ON uni.note_id = unn.note_id
         WHERE uni.image_id = ui.id) AS associated_notes
    FROM {$this->tableName} ui
    WHERE ui.id = ?
      AND usernpub = ?
    ";

    try {
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('is', $fileId, $npub);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result === false) {
        throw new Exception("Error: " . $this->db->error);
      }

      $file = $result->fetch_assoc();
      $stmt->close();

      return $file;
    } catch (Exception $e) {
      error_log("Exception: " . $e->getMessage());
      throw $e;
    }
  }
}
