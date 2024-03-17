<?php
/* Temporary class to allow for a quick and simple browsing and pagination
  * of the freely uploaded gif files. This class will be replaced by
  * a proper search of the suitable gifs.
  */

/* Table:
uploads_data;
+-----------------+-----------------------------------------------+------+-----+------------------------------+-------------------+
| Field           | Type                                          | Null | Key | Default                      | Extra             |
+-----------------+-----------------------------------------------+------+-----+------------------------------+-------------------+
| id              | int                                           | NO   | PRI | NULL                         | auto_increment    |
| filename        | varchar(255)                                  | NO   | UNI | NULL                         |                   |
| approval_status | enum('approved','pending','rejected','adult') | YES  | MUL | NULL                         |                   |
| upload_date     | timestamp                                     | YES  | MUL | CURRENT_TIMESTAMP            | DEFAULT_GENERATED |
| metadata        | json                                          | YES  |     | NULL                         |                   |
| file_size       | bigint                                        | YES  |     | NULL                         |                   |
| type            | enum('picture','video','unknown','profile')   | YES  |     | NULL                         |                   |
| media_width     | int                                           | YES  |     | 0                            |                   |
| media_height    | int                                           | YES  |     | 0                            |                   |
| blurhash        | varchar(255)                                  | YES  |     | LEHV6nWB2yk8pyo0adR*.7kCMdnj |                   |
| file_extension  | varchar(10)                                   | YES  |     | NULL                         |                   |
| usernpub        | varchar(255)                                  | YES  |     | NULL                         |                   |
| mime            | varchar(255)                                  | YES  |     | NULL                         |                   |
+-----------------+-----------------------------------------------+------+-----+------------------------------+-------------------+
*/

class GifBrowser
{
  // This class will do it all, since we do not want to keep it long term.
  // Expect duplicate code and direct invocation of DB queries if needed.

  private $db;
  private $cursor = 0;
  private $results = [];
  // Hardcoded URL prefix for the images
  private $urlPrefix = 'https://image.nostr.build/';

  public function __construct(mysqli $db)
  {
    $this->db = $db;
  }

  public function getApiResponse(int $start = 0, int $limit = 10, string $order = 'DESC', bool $random = false): string
  {
    // Fetch the gifs
    if ($random) {
      $success = $this->fetchRandomGifs($start, $limit);
    } else {
      $success = $this->fetchDateOrderedGifs($start, $limit, $order);
    }

    // Return the results as JSON
    if ($success) {
      return $this->getJsonResults();
    } else {
      return json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch gifs'
      ]);
    }
  }

  public function getJsonResults(): string
  {
    // Process the results and return them as JSON
    /*
    * Sample output:
    * {
    *   "status": "success",
    *   "message": "Gifs fetched successfully",
    *   "cursor": 0,
    *   "count": 10,
    *   "gifs": [
    *     { "url": "https://image.nostr.build/1.gif", "bh": "LEHV6nWB2yk8pyo0adR*.7kCMdnj" },
    *     { "url": "https://image.nostr.build/2.gif", "bh": "LEHV6nWB2yk8pyo0adR*.7kCMdnj" },
    *     { "url": "https://image.nostr.build/3.gif", "bh": "LEHV6nWB2yk8pyo0adR*.7kCMdnj" },
    *     ...
    *   ]
    * }
    */
    // Check if there are any results
    if (empty($this->results)) {
      $returnVal = [
        'status' => 'error', // 'error' if something went wrong
        'message' => 'No gifs found', // Error message if status is 'error'
      ];
      return json_encode($returnVal);
    }

    // Process the results
    $results = [];
    foreach ($this->results as $result) {
      $results[] = [
        'url' => $this->urlPrefix . urlencode($result['filename']),
        'bh' => $result['blurhash']
      ];
    }

    // Construct the final JSON
    $returnVal = [
      'status' => 'success', // 'error' if something went wrong
      'message' => 'Gifs fetched successfully', // Error message if status is 'error'
      'cursor' => $this->cursor, // Optional, only if status is 'success'
      'count' => count($results), // Optional, only if status is 'success'
      'gifs' => $results // Optional, only if status is 'success'
    ];
    $ret = json_encode($returnVal);
    if ($ret === false) {
      return json_encode([
        'status' => 'error',
        'message' => 'Failed to encode JSON'
      ]);
    } else {
      return $ret;
    }
  }

  public function fetchDateOrderedGifsDesc(int $start = 0, int $limit = 10): bool
  {
    return $this->fetchDateOrderedGifs($start, $limit, 'DESC');
  }

  public function fetchDateOrderedGifsAsc(int $start = 0, int $limit = 10): bool
  {
    return $this->fetchDateOrderedGifs($start, $limit, 'ASC');
  }

  public function fetchRandomGifs(int $start = 0, int $limit = 10): bool
  {
    return $this->fetchDateOrderedGifs($start, $limit, 'DESC', true);
  }

  public function fetchDateOrderedGifs(int $start = 0, int $limit = 10, string $order = 'DESC', bool $random = false): bool
  {
    // Protect agains excessive limits
    if ($limit > 50) {
      $limit = 50;
    }

    // Allow total of 25,000 gifs to be fetched
    if ($start < 0 || $start > 500) {
      $start = 0;
    }

    // Get random offset if $random is true
    if ($random) {
      $start = rand(0, 500);
      // Randomize order
      $order = rand(0, 1) ? 'ASC' : 'DESC';
    }

    // Cache the result using AMP Cache; check cache first and then fetch from DB if not found
    // If the cache is found, return it
    $ttl = 60 * 10; // 10 minutes
    $cacheKey = 'gifs_' . $start . '_' . $limit . '_' . $order . '_' . ($random ? 'random' : 'date');
    if (apcu_exists($cacheKey)) {
      $this->results = apcu_fetch($cacheKey);
      if ($this->results !== false) {
        error_log('Cache hit for ' . $cacheKey);
        return true;
      }
    }

    $sql = "SELECT filename, blurhash FROM uploads_data WHERE approval_status = 'approved' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date " . $order . " LIMIT ? OFFSET ?";
    try {
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('ii', $limit, $start);
      $stmt->execute();
      $result = $stmt->get_result();
      $this->results = $result->fetch_all(MYSQLI_ASSOC);
      $this->cursor = $start + $limit;
      // Cache the result
      apcu_store($cacheKey, $this->results, $ttl);
    } catch (Exception $e) {
      error_log('Error fetching gifs: ' . $e->getMessage());
      return false;
    } finally {
      $stmt->close();
    }
    return true;
  }
}
