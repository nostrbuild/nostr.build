<?php
// Use centralized config
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

/*
Main class to work with accounts
For reference:
desc users;
+------------------+--------------+------+-----+-------------------+-------------------+
| Field            | Type         | Null | Key | Default           | Extra             |
+------------------+--------------+------+-----+-------------------+-------------------+
| id               | int          | NO   | PRI | NULL              | auto_increment    |
| usernpub         | varchar(70)  | NO   | UNI | NULL              |                   |
| password         | varchar(255) | NO   |     | NULL              |                   |
| nym              | varchar(64)  | NO   |     | NULL              |                   |
| wallet           | varchar(255) | NO   |     | NULL              |                   |
| ppic             | varchar(255) | NO   |     | NULL              |                   |
| paid             | varchar(255) | NO   |     | NULL              |                   |
| acctlevel        | int          | NO   |     | NULL              |                   |
| flag             | varchar(10)  | NO   |     | NULL              |                   |
| created_at       | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| accflags         | json         | YES  |     | NULL              |                   |
| plan_start_date  | datetime     | YES  |     | NULL              |                   |
| npub_verified    | tinyint(1)   | NO   |     | 0                 |                   |
| allow_npub_login | tinyint(1)   | NO   |     | 0                 |                   |
+------------------+--------------+------+-----+-------------------+-------------------+

 desc users_images;
+--------------+--------------+------+-----+------------------------------+-------------------+
| Field        | Type         | Null | Key | Default                      | Extra             |
+--------------+--------------+------+-----+------------------------------+-------------------+
| id           | int          | NO   | PRI | NULL                         | auto_increment    |
| usernpub     | varchar(70)  | NO   | MUL | NULL                         |                   |
| image        | varchar(255) | NO   | MUL | NULL                         |                   |
| folder       | varchar(255) | NO   |     | NULL                         |                   |
| flag         | varchar(10)  | NO   |     | NULL                         |                   |
| created_at   | datetime     | YES  |     | CURRENT_TIMESTAMP            | DEFAULT_GENERATED |
| file_size    | bigint       | NO   |     | 0                            |                   |
| folder_id    | int          | YES  | MUL | NULL                         |                   |
| media_width  | int          | YES  |     | 0                            |                   |
| media_height | int          | YES  |     | 0                            |                   |
| blurhash     | varchar(255) | YES  |     | LEHV6nWB2yk8pyo0adR*.7kCMdnj |                   |
| mime_type    | varchar(255) | YES  |     | application/octet-stream     |                   |
| sha256_hash  | varchar(255) | YES  |     | NULL                         |                   |
+--------------+--------------+------+-----+------------------------------+-------------------+
*/

class DuplicateUserException extends Exception
{
}
class InvalidAccountLevelException extends Exception
{
}

enum AccountLevel: int
{
  case Invalid = -1;
  case Unverified = 0;
  case Creator = 1;
  case Professional = 2;
  case Viewer = 4;
  case Starter = 5;
  case Moderator = 89;
  case Admin = 99;
}

/**
 * Summary of Account
 */
class Account
{
  /**
   * Summary of npub
   * @var string
   */
  private string $npub;
  /**
   * Summary of account
   * @var array
   */
  private array $account;
  /**
   * Summary of db
   * @var mysqli
   */
  private mysqli $db;

  /**
   * Summary of __construct
   * @param string $npub
   * @param mysqli $db
   */
  public function __construct(string $npub, mysqli $db)
  {
    $this->npub = trim($npub);
    $this->db = $db;
    // Populate account data
    $this->fetchAccountData();
  }

  /**
   * Summary of fetchAccountData
   * @throws \Exception
   * @return void
   */
  private function fetchAccountData(): void
  {
    $sql = "SELECT * FROM users WHERE usernpub = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      // You can log or handle this error as needed
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param('s', $this->npub)) {
        // Handle binding parameters error
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        // Handle execution error
        throw new Exception("Error executing statement: " . $stmt->error);
      }

      $result = $stmt->get_result();
      if (!$result) {
        // Handle getting result error
        throw new Exception("Error getting result: " . $stmt->error);
      }

      $this->account = $result->fetch_assoc() ?? [];
      if (!$this->account) {
        // Handle no matching record found
        //throw new Exception("No matching record found for npub: " . $this->npub);
        error_log("No matching record found for npub: " . $this->npub);
      }
    } finally {
      $stmt->close();
    }
  }

  /**
   * Summary of setSessionParameters
   * @return void
   */
  public function setSessionParameters(): void
  {
    $_SESSION['id'] = $this->account['id'] ?? 0;
    $_SESSION['usernpub'] = $this->npub ?? '';
    $_SESSION['acctlevel'] = $this->account['acctlevel'] ?? 0;
    $_SESSION['nym'] = $this->account['nym'] ?? '';
    $_SESSION['wallet'] = $this->account['wallet'] ?? '';
    $_SESSION['ppic'] = $this->account['ppic'] ?? '';
    $_SESSION['flag'] = $this->account['flag'] ?? '';
    $_SESSION['npub_verified'] = $this->account['npub_verified'] ?? 0;
    $_SESSION['allow_npub_login'] = $this->account['allow_npub_login'] ?? 0;

    $accFlags = json_decode($this->account['accflags'] ?? '{}', true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $_SESSION['accflags'] = $accFlags;
    } else {
      $_SESSION['accflags'] = [];
    }
  }

  public function getAccountFlags(): array
  {
    return json_decode($this->account['accflags'], true);
  }

  public function setAccountFlags(array $flags): void
  {
    $this->updateAccount(accflags: $flags);
  }

  public function getAccountFlag(string $flag): bool
  {
    $flags = $this->getAccountFlags();
    return isset($flags[$flag]) ? $flags[$flag] : false;
  }

  public function setAccountFlag(string $flag, bool $value): void
  {
    $flags = $this->getAccountFlags();
    $flags[$flag] = $value;
    $this->setAccountFlags($flags);
  }

  public function updateAccountDataFromNostrApi(bool $force = false, bool $update_db = true): void
  {
    $apiQueryUrl = SiteConfig::getNostrApiBaseUrl() . urlencode($this->npub);

    // Initialize and set cURL options
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $apiQueryUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);

    // Execute cURL and close
    $response = curl_exec($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($response === false || curl_errno($ch) !== CURLE_OK) {
      error_log("Error fetching account data from Nostr API");
      return;
    }

    // Decode JSON
    $responseData = json_decode($response ?? '{}');
    if (json_last_error() !== JSON_ERROR_NONE || $responseData === null) {
      error_log("Error decoding JSON response from Nostr API: " . json_last_error_msg());
      return;
    }
    $responseData = json_decode($responseData->content);

    // Check if we should update account data
    $shouldUpdate = $responseData !== null &&
      (!empty($responseData->name) || !empty($responseData->lud16) || !empty($responseData->picture)) &&
      ($force || empty($this->account['nym']) || empty($this->account['wallet']) || empty($this->account['ppic']));

    if ($shouldUpdate) {
      $this->account['nym'] = $force ? ($responseData->name ?? $this->account['nym']) : ($this->account['nym'] ?? $responseData->name ?? null);
      $this->account['wallet'] = $force ? ($responseData->lud16 ?? $this->account['wallet']) : ($this->account['wallet'] ?? $responseData->lud16 ?? null);
      $this->account['ppic'] = $force ? ($responseData->picture ?? $this->account['ppic']) : ($this->account['ppic'] ?? $responseData->picture ?? null);

      if ($update_db) {
        $this->updateAccount(
          nym: $this->account['nym'],
          wallet: $this->account['wallet'],
          ppic: $this->account['ppic']
        );
      }
    }
  }

  /**
   * Summary of fetchAccountSpaceConsumption
   * @throws \Exception
   * @return int
   */
  private function fetchAccountSpaceConsumption(): int
  {
    $sql = "SELECT SUM(file_size) AS total FROM users_images WHERE usernpub = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      // You can log or handle this error as needed
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param('s', $this->npub)) {
        // Handle binding parameters error
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        // Handle execution error
        throw new Exception("Error executing statement: " . $stmt->error);
      }

      $result = $stmt->get_result();
      if (!$result) {
        // Handle getting result error
        throw new Exception("Error getting result: " . $stmt->error);
      }

      $total = $result->fetch_assoc()['total'] ?? 0;
    } finally {
      $stmt->close();
    }

    return $total;
  }

  /**
   * Summary of getNpub
   * @return string
   */
  public function getNpub(): string
  {
    return $this->npub;
  }

  /**
   * Summary of getAccount
   * @return array
   */
  public function getAccount(): array
  {
    return $this->account;
  }

  /**
   * Summary of getAccountLevel
   * @return AccountLevel
   */
  public function getAccountLevel(): AccountLevel
  {
    $accountLevel = isset($this->account['acctlevel']) ? $this->account['acctlevel'] : -1;
    return AccountLevel::from($accountLevel);
  }

  /*
  // Example usage:
  try {
      $db = new mysqli("localhost", "username", "password", "database");
      $npub = "exampleUser";
      $password = "examplePassword";
      $level = 0; // or some valid account level
      $account = new Account($npub, $db);
      $account->createAccount($password, $level);
      echo "Account created successfully!";
  } catch (DuplicateUserException $e) {
      echo "The usernpub is already taken.";
  } catch (InvalidAccountLevelException $e) {
      echo "The specified account level is invalid.";
  } catch (Exception $e) {
      echo "An error occurred: " . $e->getMessage();
  }
  */

  /**
   * Summary of createAccount
   * @param string $password
   * @param int $level
   * @param int $npub_verified
   * @param int $allow_npub_login
   * @throws \DuplicateUserException
   * @throws \InvalidAccountLevelException
   * @throws \Exception
   * @return void
   */
  public function createAccount(string $password, int $level = 0, int $npub_verified = 0, int $allow_npub_login = 0): void
  {
    // Preemptive check if the account already exists
    if ($this->accountExists()) {
      throw new DuplicateUserException("User with npub $this->npub already exists");
    }

    try {
      $accountLevel = AccountLevel::from($level);
    } catch (ValueError $e) {
      throw new InvalidAccountLevelException("Invalid account level: $level");
    }

    $hashed_password = password_hash(trim($password), PASSWORD_DEFAULT); // Creates a password hash

    $sql = "INSERT INTO users (usernpub, password, acctlevel, npub_verified, allow_npub_login) VALUES (?, ?, ?, ?, ?)";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      $stmt->bind_param('ssiii', $this->npub, $hashed_password, $level, $npub_verified, $allow_npub_login);
      if (!$stmt->execute()) {
        if ($this->db->errno == 1062) { // Duplicate entry error code
          throw new DuplicateUserException("User with npub $this->npub already exists (race condition)");
        } else {
          throw new Exception("Database error: " . $this->db->error);
        }
      }
    } finally {
      $stmt->close();
    }

    $this->fetchAccountData();
    $this->setSessionParameters();
  }

  /**
   * Summary of accountExists
   * @return bool
   */
  public function accountExists(): bool
  {
    return !empty($this->account);
  }

  /**
   * Summary of getRemainingSubscriptionDays
   * @throws \Exception
   * @return int
   */
  public function getRemainingSubscriptionDays(): int
  {
    $planStartDate = $this->account['plan_start_date'];
    if ($planStartDate === null) {
      throw new Exception("Plan start date is not set for this account");
    }

    $startDate = new DateTime($planStartDate);
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P1Y')); // Add one year to the start date

    $currentDate = new DateTime();

    if ($currentDate < $startDate) {
      // Subscription has not started yet
      return 0;
    } elseif ($currentDate > $endDate) {
      // Subscription has already ended
      return 0;
    } else {
      $remainingDays = $currentDate->diff($endDate)->days;
      return $remainingDays;
    }
  }

  /*
  // Call args by name
  $account->updateAccount(
    password: 'newpassword',
    nym: 'newnym'
  );
  */
  /**
   * Summary of updateAccount
   * @param string $password
   * @param string $nym
   * @param string $wallet
   * @param string $ppic
   * @param string $paid
   * @param int $acctlevel
   * @param string $flag
   * @param array $accflags
   * @param string $plan_start_date
   * @throws \Exception
   * @return void
   */
  public function updateAccount(
    string $password = null,
    string $nym = null,
    string $wallet = null,
    string $ppic = null,
    string $paid = null,
    int $acctlevel = null,
    string $flag = null,
    array $accflags = null,
    string $plan_start_date = null,
    int $npub_verified = null,
    int $allow_npub_login = null
  ) {
    $updates = [
      'password' => $password,
      'nym' => $nym,
      'wallet' => $wallet,
      'ppic' => $ppic,
      'paid' => $paid,
      'acctlevel' => $acctlevel,
      'flag' => $flag,
      'accflags' => $accflags ? json_encode($accflags) : null,
      'plan_start_date' => $plan_start_date,
      'npub_verified' => $npub_verified,
      'allow_npub_login' => $allow_npub_login,
    ];

    $sql = "UPDATE users SET ";
    $params = [];
    $types = '';

    foreach ($updates as $field => $value) {
      if ($value !== null) {
        $sql .= "$field = ?, ";
        $params[] = $value;
        $types .= is_int($value) ? 'i' : 's';

        // Update coresponding class property
        $this->account[$field] = $value;
      }
    }

    $sql = rtrim($sql, ', ');
    $sql .= " WHERE usernpub = ?";
    $params[] = $this->npub;
    $types .= 's';

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param($types, ...$params)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
    // Update session parameters
    $this->setSessionParameters();
  }

  /**
   * Summary of deleteAccount
   * @throws \Exception
   * @return void
   */
  public function deleteAccount(): void
  {
    $sql = "DELETE FROM users WHERE usernpub = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param('s', $this->npub)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
  }

  /**
   * Summary of isAccountValid
   * @return bool
   */
  public function isAccountValid(): bool
  {
    return $this->getAccountLevel() !== AccountLevel::Invalid;
  }

  /**
   * Summary of isAccountVerified
   * @return bool
   */
  public function isAccountVerified(): bool
  {
    return $this->getAccountLevel() >= AccountLevel::Unverified;
  }

  /**
   * Summary of isAccountInvalid
   * @return bool
   */
  public function isAccountInvalid(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Invalid;
  }

  /**
   * Summary of isAccountUnverified
   * @return bool
   */
  public function isAccountUnverified(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Unverified;
  }

  /**
   * Summary of isAccountCreator
   * @return bool
   */
  public function isAccountCreator(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Creator;
  }

  /**
   * Summary of isAccountProfessional
   * @return bool
   */
  public function isAccountProfessional(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Professional;
  }

  /**
   * Summary of isAccountViewer
   * @return bool
   */
  public function isAccountViewer(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Viewer;
  }

  /**
   * Summary of isAccountStarter
   * @return bool
   */
  public function isAccountStarter(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Starter;
  }

  /**
   * Summary of isAccountModerator
   * @return bool
   */
  public function isAccountModerator(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Moderator;
  }

  /**
   * Summary of isAccountAdmin
   * @return bool
   */
  public function isAccountAdmin(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Admin;
  }

  /**
   * Summary of isNpubVerified
   * @return bool
   */
  public function isNpubVerified(): bool
  {
    return $this->account['npub_verified'] === 1;
  }

  /**
   * Summary of isNpubLoginAllowed
   * @return bool
   */
  public function isNpubLoginAllowed(): bool
  {
    if ($this->isNpubVerified() === false) {
      return false;
    }
    return $this->account['allow_npub_login'] === 1;
  }

  /**
   * Summary of getRemainingStorageSpace
   * @return int
   */
  public function getRemainingStorageSpace(): int
  {
    $accountLevel = $this->account['acctlevel'];
    $limit = SiteConfig::getStorageLimit($accountLevel) ?? 0; // Default to 0 if level not found

    $usedSpace = $this->fetchAccountSpaceConsumption();

    return $limit - $usedSpace;
  }

  /**
   * Summary of hasSufficientStorageSpace
   * @param int $fileSize
   * @return bool
   */
  public function hasSufficientStorageSpace(int $fileSize): bool
  {
    $remainingSpace = $this->getRemainingStorageSpace();

    // If there's enough space for the file, including unlimited space for Admin
    return $fileSize <= $remainingSpace;
  }

  /**
   * Summary of setPlan
   * @param int $planLevel
   * @param bool $new
   * @throws \Exception
   * @return void
   */
  public function setPlan(int $planLevel, bool $new = true): void
  {
    $sql = "UPDATE users SET acctlevel = ?, plan_start_date = ? WHERE usernpub = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      $planStartDate = $new ? date('Y-m-d') : $this->account['plan_start_date'] ?? date('Y-m-d');
      if (!$stmt->bind_param('iss', $planLevel, $planStartDate, $this->npub)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
  }

  /**
   * Summary of upgradePlan
   * @param int $newPlanLevel
   * @param bool $resetDate
   * @throws \Exception
   * @return void
   */
  public function upgradePlan(int $newPlanLevel, bool $resetDate = false): void
  {
    // Fetch existing account data to get the current plan level
    $currentPlanLevel = AccountLevel::from($this->account['acctlevel']);
    $targetPlanLevel = AccountLevel::from($newPlanLevel);

    // Perform checks to ensure the upgrade is valid (e.g., new plan is higher than current plan)
    if ($this->isValidUpgrade($currentPlanLevel, $targetPlanLevel) === false) {
      throw new Exception("New plan level must be greater than the current plan level");
    }

    // Set the new plan and specify whether to reset the plan start date
    $this->setPlan($newPlanLevel, $resetDate);
  }

  /**
   * Summary of isValidUpgrade
   * @param AccountLevel $currentLevel
   * @param AccountLevel $newLevel
   * @return bool
   */
  static public function isValidUpgrade(AccountLevel $currentLevel, AccountLevel $newLevel): bool
  {
    // Array that specifies the upgrade path for each account level
    $upgradePath = [
      AccountLevel::Unverified, // 0
      AccountLevel::Moderator, // 89
      AccountLevel::Viewer, // 4
      AccountLevel::Starter, // 5
      AccountLevel::Professional, // 2
      AccountLevel::Creator, // 1
      AccountLevel::Admin, // 99
    ];

    $currentLevelIndex = array_search($currentLevel, $upgradePath);
    $newLevelIndex = array_search($newLevel, $upgradePath);

    // If both levels are found and the new level is higher in the sequence
    return $currentLevelIndex !== false && $newLevelIndex !== false && $newLevelIndex > $currentLevelIndex;
  }

  /**
   * Summary of verifyPassword
   * @param string $password
   * @return bool
   */
  public function verifyPassword(string $password): bool
  {
    $hashed_password = $this->account['password'];
    $valid = password_verify($password, $hashed_password);
    if ($valid) {
      // Update session parameters
      $this->setSessionParameters();
      // Set 'loggedin' state in session
      $_SESSION['loggedin'] = true;
    }
    return $valid;
  }

  /**
   * Summary of verifyNip98Login
   * @return bool
   */
  public function verifyNostrLogin(bool $force = false): bool
  {
    if ($force || $this->isNpubLoginAllowed() === false) {
      return false;
    }
    try {
      $this->updateAccountDataFromNostrApi();
    } catch (Exception $e) {
      error_log("Error getting user info from API: " . $e->getMessage());
    }
    $this->setSessionParameters();
    $_SESSION['loggedin'] = true;
    return true;
  }

  /**
   * Summary of allowNpubLogin
   * @param bool $allow
   * @return void
   */
  public function allowNpubLogin(bool $allow = true): void
  {
    if ($this->isNpubVerified() === true) {
      $this->updateAccount(allow_npub_login: $allow);
      $this->account['allow_npub_login'] = $allow;
      $this->setSessionParameters();
    }
  }

  /**
   * Summary of disallowNpubLogin
   * @return void
   */
  public function disallowNpubLogin(): void
  {
    $this->allowNpubLogin(false);
  }

  /**
   * Summary of verifyNpub
   * @param bool $verified
   * @return void
   */
  public function verifyNpub(bool $verified = true): void
  {
    $this->updateAccount(npub_verified: $verified);
    $this->account['npub_verified'] = $verified;
    $this->setSessionParameters();
  }

  /**
   * Summary of changePassword
   * @param string $newPassword
   * @throws \Exception
   * @return void
   */
  public function changePassword(string $newPassword): void
  {
    $hashed_password = password_hash(trim($newPassword), PASSWORD_DEFAULT); // Creates a password hash
    $this->updateAccount(password: $hashed_password);
  }
}
