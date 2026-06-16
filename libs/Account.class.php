<?php
// Use centralized config
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BlossomFrontEndAPI.class.php';

/*
Main class to work with accounts
For reference:
desc users;
+------------------------+--------------+------+-----+-------------------+-------------------+
| Field                  | Type         | Null | Key | Default           | Extra             |
+------------------------+--------------+------+-----+-------------------+-------------------+
| id                     | int          | NO   | PRI | NULL              | auto_increment    |
| usernpub               | varchar(70)  | NO   | UNI | NULL              |                   |
| password               | varchar(255) | NO   |     | NULL              |                   |
| nym                    | varchar(64)  | NO   |     | NULL              |                   |
| wallet                 | varchar(255) | NO   |     | NULL              |                   |
| ppic                   | varchar(255) | NO   |     | NULL              |                   |
| paid                   | varchar(255) | NO   |     | NULL              |                   |
| acctlevel              | int          | NO   |     | NULL              |                   |
| flag                   | varchar(10)  | NO   |     | NULL              |                   |
| created_at             | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| accflags               | json         | YES  |     | NULL              |                   |
| plan_start_date        | datetime     | YES  |     | NULL              |                   |
| npub_verified          | tinyint(1)   | NO   |     | 0                 |                   |
| allow_npub_login       | tinyint(1)   | NO   |     | 0                 |                   |
| pbkdf2_password        | varchar(255) | YES  |     | NULL              |                   |
| plan_until_date        | datetime     | YES  |     | NULL              |                   |
| last_notification_date | datetime     | YES  |     | NULL              |                   |
| subscription_period    | varchar(10)  | YES  |     | 1y                |                   |
| default_folder         | varchar(255) | YES  |     | NULL              |                   |
| addon_storage          | bigint       | YES  |     | 0                 |                   |
| referral_code          | varchar(14)  | YES  | UNI | NULL              |                   |
| nl_sub_activated_date  | datetime     | YES  |     | NULL              |                   |
| nl_sub_activation_id   | varchar(255) | YES  |     | NULL              |                   |
| nl_sub_activation_return_value | json | YES  |     | NULL              |                   |
| deletion_status        | varchar(10)  | NO   |     | none              |                   |
| deletion_requested_at  | datetime     | YES  |     | NULL              |                   |
| delete_after           | datetime     | YES  |     | NULL              |                   |
+------------------------+--------------+------+-----+-------------------+-------------------+
(deletion_* columns added for self-service account deletion — see
 requestDeletion/cancelDeletion below. Run this migration before enabling the
 enable-account-deletion flag — copy the statement as-is:

ALTER TABLE users
  ADD COLUMN deletion_status varchar(10) NOT NULL DEFAULT 'none',
  ADD COLUMN deletion_requested_at datetime NULL,
  ADD COLUMN delete_after datetime NULL;
)

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

class DuplicateUserException extends Exception {}
class InvalidAccountLevelException extends Exception {}

enum AccountLevel: int
{
  case Invalid = -1;
  case Unverified = 0;
  case Advanced = 10;
  case Creator = 1;
  case Professional = 2;
  case Purist = 3; // New Purist level
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
   * UploadsData class instance
   * @var UploadsData
   */
  private UploadsData $uploadsData;

  // Blossom frontend api
  private BlossomFrontEndAPI $blossomFrontEndAPI;

  /**
   * Summary of __construct
   * @param string $npub
   * @param mysqli $db
   */
  public function __construct(string $npub, mysqli $db)
  {
    $this->npub = trim($npub);
    $this->db = $db;
    $this->uploadsData = new UploadsData($db);
    // Populate account data
    $this->fetchAccountData();
    $this->blossomFrontEndAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
  }

  /**
   * Build an Account from the stable `uuid_id` instead of the npub. Resolves
   * uuid_id -> usernpub, then constructs through the normal npub path so all
   * existing (npub-keyed) Account logic keeps working unchanged. Returns null
   * when no account has that uuid. Used by the accounts Worker, which addresses
   * users by their stable uuid (npub is a mutable attribute).
   *
   * @param string $uuid
   * @param mysqli $db
   * @return self|null
   */
  public static function fromUuid(string $uuid, mysqli $db): ?self
  {
    $uuid = trim($uuid);
    if ($uuid === '') {
      return null;
    }
    $stmt = $db->prepare("SELECT usernpub FROM users WHERE uuid_id = ? LIMIT 1");
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $db->error);
    }
    try {
      $stmt->bind_param('s', $uuid);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
    } finally {
      $stmt->close();
    }
    if (!$row || empty($row['usernpub'])) {
      return null;
    }
    return new self((string) $row['usernpub'], $db);
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
    $this->setSessionParameters();
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
    $_SESSION['addon_storage'] = $this->account['addon_storage'] ?? 0;
    $_SESSION['referral_code'] = $this->account['referral_code'] ?? '';
    $_SESSION['planexpired'] = $this->getRemainingSubscriptionDays() === 0 ? true : false;

    $accFlags = json_decode($this->account['accflags'] ?? '{}', true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $_SESSION['accflags'] = $accFlags;
    } else {
      $_SESSION['accflags'] = [];
    }
  }

  public function getAccountReferralCode(): string
  {
    return $this->account['referral_code'] ?? '';
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

    // Check if we should update account data
    $shouldUpdate = $force || empty($this->account['nym']) || empty($this->account['wallet']) || empty($this->account['ppic']);
    // If we don't need to update, return early
    if ($shouldUpdate === false) {
      return;
    }

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
    $curlErrNo = curl_errno($ch);
    $ch = null;

    // Handle cURL errors
    if ($response === false || $curlErrNo !== CURLE_OK) {
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
      (!empty($responseData->name) || !empty($responseData->lud16) || !empty($responseData->picture));

    if ($shouldUpdate) {
      $this->account['nym'] = $force ? ($responseData->name ?? $this->account['nym']) : (empty($this->account['nym']) ? $responseData->name ?? '' : $this->account['nym'] ?? '');
      $this->account['wallet'] = $force ? ($responseData->lud16 ?? $this->account['wallet']) : (empty($this->account['wallet']) ? $responseData->lud16 ?? '' : $this->account['wallet'] ?? '');
      $this->account['ppic'] = $force ? ($responseData->picture ?? $this->account['ppic']) : (empty($this->account['ppic']) ? $responseData->picture ?? '' : $this->account['ppic'] ?? '');

      if ($update_db) {
        $this->updateAccount(
          nym: $this->account['nym'],
          wallet: $this->account['wallet'],
          ppic: $this->account['ppic']
        );
        $this->setSessionParameters();
        $newData = $this->getAccountInfo();
        $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
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

  /**
   * Summary of getAccountLevel
   * @return integer
   */
  public function getAccountLevelInt(): int
  {
    $accountLevel = isset($this->account['acctlevel']) ? $this->account['acctlevel'] : -1;
    return (int) $accountLevel;
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
  public function createAccount(string $password, int $level = 0, int $npub_verified = 0, int $allow_npub_login = 1): void
  {
    // Preemptive check if the account already exists
    if ($this->accountExists()) {
      throw new DuplicateUserException("User with npub $this->npub already exists");
    }
    error_log("Creating account for npub: $this->npub, level: $level, npub_verified: $npub_verified, allow_npub_login: $allow_npub_login");

    try {
      $accountLevel = AccountLevel::from($level);
    } catch (ValueError $e) {
      throw new InvalidAccountLevelException("Invalid account level: $level");
    }

    $hashed_password = password_hash(trim($password), PASSWORD_DEFAULT); // Creates a password hash
    $pbkdf2_hashed_password = hashPasswordPBKDF2(trim($password)); // Creates a PBKDF2 password hash

    $sql = "INSERT INTO users (usernpub, password, pbkdf2_password, acctlevel, npub_verified, allow_npub_login, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      // Generate referral code
      $referralCode = generateUniqueCode();
      $stmt->bind_param('sssiiis', $this->npub, $hashed_password, $pbkdf2_hashed_password, $level, $npub_verified, $allow_npub_login, $referralCode);
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

    // Update account data from API
    try {
      $this->updateAccountDataFromNostrApi();
    } catch (Exception $e) {
      error_log("Error getting user info from API: " . $e->getMessage());
    }

    $this->fetchAccountData();
    $this->setSessionParameters();
    // Send update to Blossom API
    $newData = $this->getAccountInfo();
    $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
  }

  /**
   * Record the user's acceptance of the Terms of Service + Privacy Policy.
   *
   * Clickwrap evidence: the app gates account creation on an explicit "I agree"
   * checkbox, then passes the accepted document version (the documents' effective
   * date, e.g. "2026-06-15") here. We stamp the version + a server timestamp on
   * the user row so assent is provable and version-aware (a newer version than
   * the stored one is the signal to ask the user to re-accept).
   *
   * @param string $version The accepted legal-document version identifier.
   * @return void
   */
  public function recordLegalAcceptance(string $version): void
  {
    $sql = "UPDATE users SET legal_accepted_at = NOW(), legal_version = ? WHERE usernpub = ?";
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing legal-acceptance statement: " . $this->db->error);
    }
    try {
      $stmt->bind_param('ss', $version, $this->npub);
      if (!$stmt->execute()) {
        throw new Exception("Database error recording legal acceptance: " . $this->db->error);
      }
    } finally {
      $stmt->close();
    }
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
    $planStartDate = array_key_exists('plan_start_date', $this->account)
      ? $this->account['plan_start_date']
      : null;
    $planEndDate = array_key_exists('plan_until_date', $this->account)
      ? $this->account['plan_until_date']
      : null;
    if ($planStartDate === null || $planEndDate === null) {
      error_log("Plan start date is not set for this account");
      return 0;
    }

    $startDate = new DateTime($planStartDate);
    $endDate = new DateTime($planEndDate);

    $currentDate = new DateTime();

    // Account for special account levels Admin, Moderator and return 9,999 days
    if ($this->getAccountLevel() === AccountLevel::Admin || $this->getAccountLevel() === AccountLevel::Moderator) {
      return 9999;
    }

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

  public function getDaysPastSubscriptionExpiration(): int
  {
    $planEndDate = $this->account['plan_until_date'];
    if ($planEndDate === null) {
      error_log("Plan end date is not set for this account");
      return 0;
    }

    $endDate = new DateTime($planEndDate);
    $currentDate = new DateTime();

    // Account for special account levels Admin, Moderator and return 0 days
    if ($this->getAccountLevel() === AccountLevel::Admin || $this->getAccountLevel() === AccountLevel::Moderator) {
      return 0;
    }

    if ($currentDate < $endDate) {
      // Subscription has not ended yet
      return 0;
    } else {
      $daysPastExpiration = $endDate->diff($currentDate)->days;
      return $daysPastExpiration;
    }
  }

  public function getDaysUntilSubscriptionExpiration(): int
  {
    $planEndDate = $this->account['plan_until_date'];
    if ($planEndDate === null) {
      error_log("Plan end date is not set for this account");
      return 0;
    }

    $endDate = new DateTime($planEndDate);
    $currentDate = new DateTime();

    // Account for special account levels Admin, Moderator and return 9,999 days
    if ($this->getAccountLevel() === AccountLevel::Admin || $this->getAccountLevel() === AccountLevel::Moderator) {
      return 9999;
    }

    if ($currentDate > $endDate) {
      // Subscription has already ended
      return 0;
    } else {
      $daysUntilExpiration = $currentDate->diff($endDate)->days;
      return $daysUntilExpiration;
    }
  }

  public function getDaysPastLastNotification(): int
  {
    $lastNotificationDate = $this->account['last_notification_date'];
    if ($lastNotificationDate === null) {
      error_log("Last notification date is not set for this account");
      return -1;
    }

    $notificationDate = new DateTime($lastNotificationDate);
    $currentDate = new DateTime();

    $daysPastNotification = $notificationDate->diff($currentDate)->days;
    return $daysPastNotification;
  }

  public function updateLastNotificationDate(): void
  {
    $this->updateAccount(last_notification_date: date('Y-m-d'));
  }

  public function getRemainingSubscriptionPeriod(): string
  {
    $remainingDays = $this->getRemainingSubscriptionDays();
    return match (true) {
      $remainingDays <= 365 && $remainingDays > 0 => '1y',
      $remainingDays <= 730 && $remainingDays > 365 => '2y',
      $remainingDays <= 1095 && $remainingDays > 730 => '3y',
      default => '1y',
    };
  }

  public function getSubscriptionPeriod(): string
  {
    return $this->account['subscription_period'] ?? '1y';
  }

  public function isExpired(): bool
  {
    return $this->getRemainingSubscriptionDays() === 0;
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
   * @param string|null $password
   * @param string|null $pbkdf2_password
   * @param string|null $nym
   * @param string|null $wallet
   * @param string|null $ppic
   * @param string|null $paid
   * @param int|null $acctlevel
   * @param string|null $flag
   * @param array|null $accflags
   * @param string|null $plan_start_date
   * @param string|null $plan_until_date
   * @param string|null $last_notification_date
   * @param int|null $npub_verified
   * @param int|null $allow_npub_login
   * @param string|null $subscription_period
   * @param string|null $default_folder
   * @param string|null $nl_sub_activated_date
   * @param string|null $nl_sub_activation_id
   * @param array|null $nl_sub_activation_return_value
   * @throws \Exception
   * @return void
   */
  public function updateAccount(
    ?string $password = null,
    ?string $pbkdf2_password = null,
    ?string $nym = null,
    ?string $wallet = null,
    ?string $ppic = null,
    ?string $paid = null,
    ?int $acctlevel = null,
    ?string $flag = null,
    ?array $accflags = null,
    ?string $plan_start_date = null,
    ?string $plan_until_date = null,
    ?string $last_notification_date = null,
    ?int $npub_verified = null,
    ?int $allow_npub_login = null,
    ?string $subscription_period = null,
    ?string $default_folder = null,
    ?string $nl_sub_activated_date = null,
    ?string $nl_sub_activation_id = null,
    ?array $nl_sub_activation_return_value = null
  ) {
    $updates = [
      'password' => $password,
      'pbkdf2_password' => $pbkdf2_password,
      'nym' => $nym,
      'wallet' => $wallet,
      'ppic' => $ppic,
      'paid' => $paid,
      'acctlevel' => $acctlevel,
      'flag' => $flag,
      'accflags' => $accflags ? json_encode($accflags) : null,
      'plan_start_date' => $plan_start_date,
      'plan_until_date' => $plan_until_date,
      'last_notification_date' => $last_notification_date,
      'npub_verified' => $npub_verified,
      'allow_npub_login' => $allow_npub_login,
      'subscription_period' => $subscription_period,
      'default_folder' => $default_folder,
      'nl_sub_activated_date' => $nl_sub_activated_date,
      'nl_sub_activation_id' => $nl_sub_activation_id,
      'nl_sub_activation_return_value' => $nl_sub_activation_return_value ? json_encode($nl_sub_activation_return_value) : null
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

    // DEBUG
    error_log("Account update SQL: $sql" . PHP_EOL);
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      error_log("Error preparing statement: " . $this->db->error);
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param($types, ...$params)) {
        error_log("Error binding parameters: " . $stmt->error);
        throw new Exception("Error binding parameters: " . $stmt->error);
      }

      if (!$stmt->execute()) {
        error_log("Error executing statement: " . $stmt->error);
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } catch (Exception $e) {
      error_log("Error updating account: " . $e->getMessage());
      throw $e;
    } finally {
      $stmt->close();
    }
    //error_log("Account updated successfully, SQL: $sql , Params: " . print_r($params, true) . PHP_EOL);
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

  // =========================================================================
  // ACCOUNT DELETION (self-service, EXPIRED-accounts-only, 30-day window)
  // -------------------------------------------------------------------------
  // The account Worker orchestrates the lifecycle (validates eligibility +
  // re-auth + typed phrase, kicks the final backup, runs the media purge, then
  // performs the terminal WIPE). PHP just records the pending state + deadline
  // and re-checks the expired invariant. This is NOT deleteAccount() above (a
  // hard row DELETE): the terminal step is a wipe-to-free that keeps usernpub +
  // the row + login intact, so these methods never touch the npub.
  // =========================================================================

  public function getDeletionStatus(): string
  {
    return $this->account['deletion_status'] ?? 'none';
  }

  public function getDeletionDeleteAfter(): ?string
  {
    return $this->account['delete_after'] ?? null;
  }

  /**
   * Mark an EXPIRED account for deletion: a reversible window (default 30 days)
   * after which the Worker wipes it. Idempotent — a repeat request keeps the
   * original deadline. Returns 'pending' (newly scheduled), 'noop-pending'
   * (already scheduled), or 'rejected-not-expired' (active plan; never schedule
   * — active accounts are out of scope this iteration).
   * @throws \Exception on DB failure
   */
  public function requestDeletion(int $windowDays = 30): string
  {
    $this->fetchAccountData();
    if (!$this->isExpired()) {
      return 'rejected-not-expired';
    }
    if (($this->account['deletion_status'] ?? 'none') === 'pending') {
      return 'noop-pending';
    }
    $now = date('Y-m-d H:i:s');
    $deleteAfter = date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));
    $stmt = $this->db->prepare(
      "UPDATE users SET deletion_status = 'pending', deletion_requested_at = ?, delete_after = ? WHERE usernpub = ?"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('sss', $now, $deleteAfter, $this->npub)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }
      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return 'pending';
  }

  /**
   * Clear a pending deletion (back to normal). Two callers: a successful
   * renewal/upgrade (the user's only self-service exit, hooked in setPlan) and
   * the admin override (legal hold / user asked for more time / support).
   * Idempotent + safe when nothing is pending. Uses its own UPDATE because
   * updateAccount() skips null-valued fields (it can't reset to NULL).
   * @throws \Exception on DB failure
   */
  public function cancelDeletion(): void
  {
    $stmt = $this->db->prepare(
      "UPDATE users SET deletion_status = 'none', deletion_requested_at = NULL, delete_after = NULL WHERE usernpub = ?"
    );
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
    $this->account['deletion_status'] = 'none';
    $this->account['deletion_requested_at'] = null;
    $this->account['delete_after'] = null;
  }

  /**
   * Terminal account-deletion WIPE (the 30-day timer elapsed): reset the account
   * to an empty free shell while KEEPING usernpub + the row + login intact. Blanks
   * the profile view fields (nym/wallet/ppic), drops to free (acctlevel 0), clears
   * the plan dates, and clears the deletion flags. This is NOT deleteAccount() (a
   * hard row DELETE) and NEVER touches usernpub/uuid_id/password - the user can
   * still log in afterward, to a blank free account. The MEDIA purge is done
   * separately (ImageCatalogManager::deleteAllUserImages) BEFORE this is called.
   * @throws \Exception on DB failure
   */
  public function wipeForDeletion(): void
  {
    $stmt = $this->db->prepare(
      "UPDATE users SET nym = NULL, wallet = NULL, ppic = NULL, acctlevel = 0,
         plan_start_date = NULL, plan_until_date = NULL, subscription_period = NULL,
         deletion_status = 'none', deletion_requested_at = NULL, delete_after = NULL
       WHERE usernpub = ?"
    );
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
    $this->fetchAccountData();
    // Keep Blossom in sync with the now-free tier (same as setPlan does).
    $this->blossomFrontEndAPI->updateAccount($this->npub, $this->getAccountInfo());
  }

  /**
   * Is Account NostrLand Plus Subscription eligible?
   * @return bool
   */
  public function isAccountNostrLandPlusEligible(): bool
  {
    // Account has to have at least 30 days remaining
    $remainingDays = $this->getRemainingSubscriptionDays();

    return $remainingDays >= 30 && (
           $this->getAccountLevel() === AccountLevel::Creator ||
           $this->getAccountLevel() === AccountLevel::Advanced ||
           $this->getAccountLevel() === AccountLevel::Admin);
  }
  /**
   * Get account plan until date
   * @return string|null
   */
  public function getAccountPlanUntilDate(): ?string
  {
    return $this->account['plan_until_date'] ?? null;
  }

  /**
   * Get account numeric id
   * @return int|null
   */
  public function getAccountNumericId(): ?int
  {
    return $this->account['id'] ?? null;
  }

  /**
   * Get the account's stable uuid (users.uuid_id). Unlike the autoincrement
   * `id` (reassigned on a DB re-import) and the npub (a mutable attribute), this
   * is the durable per-user identity the accounts Worker keys its Durable
   * Objects / cookies / webhooks on.
   * @return string|null
   */
  public function getAccountUuid(): ?string
  {
    return $this->account['uuid_id'] ?? null;
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
   * Whether this account is allowed to upload right now: a valid,
   * non-expired plan AND the npub is NOT on the legacy blacklist.
   *
   * Use this — not isAccountValid() — anywhere we decide whether to treat a
   * request as a "pro" upload (i.e. when computing accountUploadEligible).
   * isAccountValid() is intentionally narrower: a banned user can still log
   * in, view their plan, manage billing, etc. They just can't upload.
   */
  public function isUploadEligible(): bool
  {
    if (!$this->isAccountValid()) return false;
    if ($this->isExpired()) return false;
    if ($this->isBanned()) return false;
    return true;
  }

  /**
   * Public read of the legacy npub blacklist for this account's npub.
   * Wraps UploadsData::checkBlacklisted (which delegates to LegacyBlacklist)
   * so callers can ask "is this user banned?" without poking at private
   * collaborators.
   */
  public function isBanned(): bool
  {
    return $this->uploadsData->checkBlacklisted($this->npub);
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

  public function getAccountAdditionStorage(): int
  {
    return $this->account['addon_storage'] ?? 0; // In bytes
  }

  /**
   * Summary of getRemainingStorageSpace
   * @return int
   */
  public function getRemainingStorageSpace(): int
  {
    $limit = $this->getStorageSpaceLimit();

    $usedSpace = $this->fetchAccountSpaceConsumption();

    $remaining = $limit - $usedSpace;

    return $remaining;
  }

  public function getPerFileUploadLimit(): int
  {
    // If account is level 3 (Purist) we also need to account for the SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT, which would be max for any uploads for that account
    if ($this->getAccountLevel() === AccountLevel::Purist) {
      // Min of remaining space and Purist per file upload limit
      $remainingSpace = $this->getRemainingStorageSpace();
      if ($remainingSpace < SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT) {
        return $remainingSpace;
      }
      // If remaining space is more than Purist per file upload limit, return the Purist
      return SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT;
    }
    return $this->getRemainingStorageSpace();
  }

  public function getUsedStorageSpace(): int
  {
    return $this->fetchAccountSpaceConsumption();
  }

  public function getStorageSpaceLimit(): int
  {
    $accountLevel = array_key_exists('acctlevel', $this->account)
      ? $this->account['acctlevel']
      : 0;
    $accountAddonStorage = $this->getAccountAdditionStorage();
    return SiteConfig::getStorageLimit($accountLevel, $accountAddonStorage) ?? 0; // Default to 0 if level not found
  }

  public function getDefaultFolder(): string
  {
    return $this->account['default_folder'] ?? '';
  }

  /**
   * Summary of hasSufficientStorageSpace
   * @param int $fileSize
   * @return bool
   */
  public function hasSufficientStorageSpace(int $fileSize): bool
  {
    $remainingSpace = $this->getPerFileUploadLimit();

    // If there's enough space for the file, including unlimited space for Admin
    return $fileSize <= $remainingSpace;
  }

  /**
   * Get NostrLand subscription activated date
   * @return string|null
   */
  public function getNlSubActivatedDate(): ?string
  {
    return $this->account['nl_sub_activated_date'] ?? null;
  }

  /**
   * Get NostrLand subscription activation ID
   * @return string|null
   */
  public function getNlSubActivationId(): ?string
  {
    return $this->account['nl_sub_activation_id'] ?? null;
  }

  /**
   * Get NostrLand subscription activation return value
   * @return array|null
   */
  public function getNlSubActivationReturnValue(): ?array
  {
    $value = $this->account['nl_sub_activation_return_value'] ?? null;
    if ($value === null) {
      return null;
    }
    return json_decode($value, true);
  }

  /**
   * Set NostrLand subscription activation data
   * @param string $activationId
   * @param array $returnValue
   * @return void
   */
  public function setNlSubActivation(string $activationId, array $returnValue): void
  {
    $this->updateAccount(
      nl_sub_activated_date: date('Y-m-d H:i:s'),
      nl_sub_activation_id: $activationId,
      nl_sub_activation_return_value: $returnValue
    );
  }

  /**
   * Check if NostrLand subscription has been activated for current plan
   * @return bool
   */
  public function hasNlSubActivation(): bool
  {
    return !empty($this->account['nl_sub_activation_id']);
  }

  /**
   * Get parsed NostrLand subscription information
   *
   * @return array|null Parsed subscription info or null if no activation
   */
  public function getNlSubInfo(): ?array
  {
    if (!$this->hasNlSubActivation()) {
      return null;
    }

    try {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrLand.class.php';
      $nostrLand = new NostrLand($this->npub, $this->db);
      return $nostrLand->getSubscriptionInfo();
    } catch (Exception $e) {
      error_log("Failed to get NostrLand subscription info for npub: " . $this->npub . " - " . $e->getMessage());
      return null;
    }
  }

  /**
   * Clear NostrLand subscription activation data (for testing or reset)
   * @return void
   */
  public function clearNlSubActivation(): void
  {
    $this->updateAccount(
      nl_sub_activated_date: null,
      nl_sub_activation_id: null,
      nl_sub_activation_return_value: null
    );
  }

  /**
   * Summary of setPlan
   * @param int $planLevel
   * @param string $period
   * @param bool $new
   * @throws \Exception
   * @return void
   */
  /**
   * Apply/renew a plan. Returns the outcome so a caller (the account Worker,
   * which is the authoritative settlement source) can tell a PAID-but-not-applied
   * order from a real success:
   *   'applied'                 - the DB row was updated.
   *   'noop-current'            - already at the intended state (idempotent re-run).
   *   'rejected-renewal-window' - renewal blocked: too many days remaining.
   *   'rejected-no-change'      - the renewal WHERE guard matched no row.
   */
  public function setPlan(int $planLevel, string $period = '1y', bool $new = true, ?string $planUntilOverride = null): string
  {
    // Refresh account data to ensure we have latest DB state for deterministic calculations
    $this->fetchAccountData();

    $safeRenewDays = 181; // 180 days + 1 day buffer
    error_log("Setting plan level: $planLevel, period: $period, new: " . ($new ? 'true' : 'false') . PHP_EOL);

    // Validate and sanitize period input
    if (!in_array($period, ['1y', '2y', '3y'])) {
      $period = '1y';
    }

    // Downgrade-on-renew: the account Worker computed the exact new expiry
    // (purchased term + the converted bonus days from the user's remaining
    // value, see #/lib/plans/downgrade). We store it VERBATIM - the Worker is the
    // authoritative settlement source - but the INVARIANTS stay here: it must be
    // a genuine downgrade, the account must be inside the renewal window, and the
    // date must be a sane near-future value. Self-contained branch so the normal
    // signup/renewal/upgrade date math below is untouched.
    if ($planUntilOverride !== null) {
      require_once __DIR__ . '/Plans.class.php';
      $currentLevel = (int)($this->account['acctlevel'] ?? 0);
      // Tier RANK is by PRICE, not by the acctlevel integer (Creator=1,
      // Professional=2, Purist=3, Advanced=10 - NOT rank-ordered). Compare the
      // same originalPrices the app's isDowngradeTarget uses.
      $currentPrice = Plans::$originalPrices[$currentLevel] ?? 0;
      $targetPrice = Plans::$originalPrices[$planLevel] ?? 0;

      // Parse + bound the explicit expiry the Worker computed.
      $overrideTs = strtotime($planUntilOverride);
      if ($overrideTs === false) {
        error_log("set-plan override rejected: unparseable date '{$planUntilOverride}' for npub: " . $this->npub);
        return 'rejected-bad-date';
      }
      $today = date('Y-m-d');
      $maxDate = date('Y-m-d', strtotime($today . ' +4 years +60 days'));
      $overrideEnd = date('Y-m-d', $overrideTs);
      if ($overrideEnd <= $today || $overrideEnd > $maxDate) {
        error_log("set-plan override rejected: date '{$overrideEnd}' out of bounds for npub: " . $this->npub);
        return 'rejected-bad-date';
      }

      // Idempotent re-run FIRST: a workflow retry AFTER a successful apply lands
      // here with the level already == target, which the downgrade check below
      // would otherwise mistake for a non-downgrade. Already at target tier +
      // period + expiry → success no-op.
      $existingEnd = $this->account['plan_until_date'] ?? null;
      if ($currentLevel === $planLevel
          && $existingEnd !== null && substr((string)$existingEnd, 0, 10) === $overrideEnd
          && ($this->account['subscription_period'] ?? '1y') === $period) {
        error_log("set-plan override noop (already applied) for npub: " . $this->npub);
        return 'noop-current';
      }

      // Must be a genuine downgrade BY PRICE (strictly cheaper, sold tier).
      if ($targetPrice <= 0 || $targetPrice >= $currentPrice) {
        error_log("set-plan override rejected: target {$planLevel} (\${$targetPrice}) is not a downgrade from current {$currentLevel} (\${$currentPrice}) for npub: " . $this->npub);
        return 'rejected-not-downgrade';
      }

      // Inside the renewal window (defence in depth; the app gates the same).
      if ($this->getRemainingSubscriptionDays() > $safeRenewDays) {
        error_log("set-plan override rejected: outside renewal window for npub: " . $this->npub);
        return 'rejected-renewal-window';
      }

      $overrideStart = $today;
      $stmt = $this->db->prepare("UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE usernpub = ?");
      if (!$stmt) {
        throw new Exception("Error preparing statement: " . $this->db->error);
      }
      try {
        if (!$stmt->bind_param('issss', $planLevel, $overrideStart, $overrideEnd, $period, $this->npub)) {
          throw new Exception("Error binding parameters: " . $stmt->error);
        }
        if (!$stmt->execute()) {
          throw new Exception("Error executing statement: " . $stmt->error);
        }
        if ($stmt->affected_rows === 0) {
          error_log("set-plan override: no rows updated for npub: " . $this->npub);
          return 'rejected-no-change';
        }
        $this->fetchAccountData();

        // Self-service deletion exit: a successful downgrade-on-renew reactivates
        // the account, which is the user's ONLY way to cancel a pending deletion
        // (same hook as the normal renewal/upgrade path below). WITHOUT this, a
        // downgraded account keeps deletion_status='pending' and the Worker's
        // DeletionWorkflow would wipe a now-paying customer. Idempotent no-op
        // when nothing is pending.
        if (($this->account['deletion_status'] ?? 'none') === 'pending') {
          $this->cancelDeletion();
        }
      } finally {
        $stmt->close();
        // Keep Blossom in sync with the new tier/expiry (same as the normal path).
        $newData = $this->getAccountInfo();
        $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
      }
      return 'applied';
    }

    // Prevent renewal if too much time remaining (business rule enforcement)
    if ($new === false && $this->getRemainingSubscriptionDays() > $safeRenewDays) {
      error_log("Cannot renew plan when remaining subscription days is more than {$safeRenewDays} days, current remaining days: " .
        $this->getRemainingSubscriptionDays() . " days for npub: " . $this->npub);
      return 'rejected-renewal-window';
    }

    // Calculate what the plan should be to check if already applied (idempotent behavior)
    $hasExistingPlan = !empty($this->account['plan_until_date']);
    
    // `$new` already encodes the intent: a NEW TERM (signup OR upgrade) starts
    // today; a renewal ($new=false) extends the existing term. Upgrades
    // legitimately have an existing plan, so do NOT re-collapse them into a
    // renewal - the proration already credited their unused time toward a fresh
    // term. Every caller classifies a same-level purchase as 'renewal'
    // ($new=false), so this never resets a renewing user's remaining time.
    $isActuallyNew = $new;
    $isExpiredPlan = $this->isExpired();
    
    $expectedStartDate = ($isActuallyNew || $isExpiredPlan) ? date('Y-m-d') : $this->account['plan_start_date'];
    
    // For deterministic end date calculation, use a consistent base date
    if ($isActuallyNew || $isExpiredPlan) {
      $baseDate = date('Y-m-d');
    } else {
      // For renewals of active plans: calculate from original plan end date to maintain consistency
      $baseDate = $this->account['plan_until_date'];
    }
    
    // Convert period to date interval string
    $periodDuration = match ($period) {
      '1y' => '+1 year',
      '2y' => '+2 years',
      '3y' => '+3 years',
      default => '+1 year',
    };
    
    $expectedEndDate = date('Y-m-d', strtotime($baseDate . ' ' . $periodDuration));
    
    // Check if plan already matches expected state (idempotent check)
    $currentLevel = $this->account['acctlevel'] ?? 0;
    $currentPeriod = $this->account['subscription_period'] ?? '1y';
    $currentEndDate = $this->account['plan_until_date'] ?? null;
    $currentStartDate = $this->account['plan_start_date'] ?? null;
    
    // For truly new accounts, expired renewals, or if plan matches exactly what we would set
    if ($currentLevel == $planLevel && $currentPeriod == $period && 
        (($isActuallyNew && $currentEndDate !== null) || 
         ($isExpiredPlan && $currentEndDate == $expectedEndDate && $currentStartDate == $expectedStartDate) ||
         (!$isActuallyNew && !$isExpiredPlan && $currentEndDate == $expectedEndDate))) {
      error_log("Plan already set to expected state - skipping update (idempotent behavior) for npub: " . $this->npub);
      return 'noop-current';
    }

    // Proceed with update since plan doesn't match expected state
    $planStartDate = $expectedStartDate;
    $planEndDate = $expectedEndDate;

    // Fallback if date calculation fails (edge case protection)
    if ($planEndDate === false) {
      error_log("Warning: Invalid date calculation for npub: " . $this->npub);
      $planEndDate = date('Y-m-d', strtotime(date('Y-m-d') . ' ' . $periodDuration));
    }

    // Build SQL query with conditional WHERE clause for renewals  
    if ($isActuallyNew) {
      $sql = "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE usernpub = ?";
    } else {
      $sql = "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? " .
        "WHERE usernpub = ? AND plan_until_date < DATE_ADD(CURDATE(), INTERVAL {$safeRenewDays} DAY)";
    }

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    $status = 'applied';
    try {
      error_log("Account data: " . print_r($this->account, true));
      error_log("Plan start date: $planStartDate, Plan end date: $planEndDate" . PHP_EOL);

      if (!$stmt->bind_param('issss', $planLevel, $planStartDate, $planEndDate, $period, $this->npub)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }

      if ($stmt->affected_rows === 0) {
        // The conditional renewal WHERE guard (plan_until_date < now+181d) matched
        // no row: the order was PAID but the plan did NOT change. Signal it so the
        // Worker parks an admin exception instead of marking the order active.
        $status = 'rejected-no-change';
        error_log("Notice: No rows updated for npub: " . $this->npub .
          ", new: " . ($new ? 'true' : 'false') .
          ", remaining days: " . $this->getRemainingSubscriptionDays());
      } else {
        // Refresh local account data after successful DB update to maintain consistency
        $this->fetchAccountData();

        // Self-service deletion exit: a successful renewal/upgrade reactivates
        // the account, which is the user's ONLY way to cancel a pending deletion
        // (the danger-zone copy warns them cancel = pay). Idempotent no-op when
        // nothing is pending.
        if (($this->account['deletion_status'] ?? 'none') === 'pending') {
          $this->cancelDeletion();
        }

        // Trigger NostrLand renewal activation if eligible and previously activated
        if (!$isActuallyNew) { // Only for renewals, not new accounts
          try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrLand.class.php';
            $nostrLand = new NostrLand($this->npub, $this->db);
            $activationResult = $nostrLand->handlePlanRenewal();
            if ($activationResult !== null) {
              error_log("NostrLand renewal activation successful for npub: " . $this->npub);
            }
          } catch (Exception $e) {
            error_log("NostrLand renewal activation failed for npub: " . $this->npub . " - " . $e->getMessage());
          }
        }
      }
    } finally {
      $stmt->close();
      // Send update to Blossom API
      $newData = $this->getAccountInfo();
      $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
    }

    return $status;
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
      AccountLevel::Advanced, // 10
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
    $hashed_password = $this->account['password'] ?? null;
    $valid = $hashed_password === null ? false : password_verify($password, $hashed_password);
    if ($valid) {
      // Prefill PBKDF2 password hash if not set
      if (empty($this->account['pbkdf2_password'])) {
        $pbkdf2_hashed_password = hashPasswordPBKDF2(trim($password)); // Creates a PBKDF2 password hash
        $this->updateAccount(pbkdf2_password: $pbkdf2_hashed_password);
      } else {
        $valid_pbkdf2 = verifyPasswordPBKDF2(trim($password), $this->account['pbkdf2_password']);
        // Log PBKDF2 password hash verification result
        error_log("PBKDF2 password hash verification result: " . ($valid_pbkdf2 ? 'true' : 'false') . PHP_EOL);
      }
      // Update session parameters
      $this->setSessionParameters();
      // Set 'loggedin' state in session
      $_SESSION['loggedin'] = true;
    }
    return $valid;
  }

  /**
   * Summary of verifyNostrLogin
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
      // Send update to Blossom API
      $newData = $this->getAccountInfo();
      $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
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
    // Send update to Blossom API
    $newData = $this->getAccountInfo();
    $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
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
    // Also update PBKDF2 password hash
    $pbkdf2_hashed_password = hashPasswordPBKDF2(trim($newPassword)); // Creates a PBKDF2 password hash
    $this->updateAccount(password: $hashed_password, pbkdf2_password: $pbkdf2_hashed_password);
    // Send update to Blossom API
    $newData = $this->getAccountInfo();
    $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
  }

  /**
   * Changes the password for the account in a safe manner.
   *
   * This method verifies the current password before changing it to the new password.
   *
   * @param string $currentPassword The current password of the account.
   * @param string $newPassword The new password to be set for the account.
   * @return bool Returns true if the password was changed successfully, false otherwise.
   */
  public function changePasswordSafe(string $currentPassword, string $newPassword): bool
  {
    if ($this->verifyPassword($currentPassword)) {
      $this->changePassword($newPassword);
      return true;
    }
    return false;
  }

  /**
   * Return full account information, without password hashes
   * @return array
   */
  public function getAccountInfo(): array
  {
    $accountInfo = $this->account;
    // Check if the user is banned
    if ($this->uploadsData->checkBlacklisted($this->npub)) {
      $accountInfo['banned'] = true;
      // Add ban reason, which is "Repeated TOS violations or for legal reasons", which may include CSAM uploads
      $accountInfo['ban_reason'] = 'Repeated TOS violations or for legal reasons';
    } else {
      $accountInfo['banned'] = false;
      $accountInfo['ban_reason'] = '';
    }
    // Check if empty and return early
    if (empty($accountInfo)) {
      return [];
    }
    unset($accountInfo['password']);
    unset($accountInfo['pbkdf2_password']);
    // Add remaining storage space
    $accountInfo['remaining_storage_space'] = $this->getRemainingStorageSpace();
    // Add used storage space
    $accountInfo['used_storage_space'] = $this->getUsedStorageSpace();
    // Add storage space limit
    $accountInfo['storage_space_limit'] = $this->getStorageSpaceLimit();
    // Add remaining subscription days
    $accountInfo['remaining_subscription_days'] = $this->getRemainingSubscriptionDays();
    // Add NostrLand eligibility and activation status
    $accountInfo['nl_sub_eligible'] = $this->isAccountNostrLandPlusEligible();
    $accountInfo['nl_sub_activated'] = $accountInfo['nl_sub_eligible'] && $this->hasNlSubActivation();
    // Add detailed NostrLand subscription info if activated and eligible
    if ($accountInfo['nl_sub_activated']) {
      $accountInfo['nl_sub_info'] = $this->getNlSubInfo();
    }
    return $accountInfo;
  }

  // =========================================================================
  // ADMIN OVERRIDES
  // -------------------------------------------------------------------------
  // These bypass the business rules baked into setPlan() (the 180-day
  // safe-renewal window, the idempotent-skip when state matches, NostrLand
  // renewal hooks) because an administrator deliberately driving the change
  // is exactly the case those rules exist to prevent users from doing.
  // Routes that call these must already have authorized the caller as an
  // admin and validated the inputs — these methods do final defensive
  // guards but assume the route layer is doing input validation too.
  // =========================================================================

  /**
   * Admin-set the user's plan level + duration. Always sets plan_start_date
   * to today and plan_until_date to today + period. Demotion to level 0
   * (Free) clears plan dates so the account looks like a fresh free user.
   *
   * @param int    $level  Account level (validated against AccountLevel enum)
   * @param string $period One of '1y' | '2y' | '3y'
   * @return array{planUntilDate: ?string, level: int}
   * @throws InvalidArgumentException on bad inputs
   * @throws Exception on DB failure
   */
  public function adminSetPlan(int $level, string $period): array
  {
    try {
      AccountLevel::from($level); // throws ValueError if not a known level
    } catch (ValueError $e) {
      throw new InvalidArgumentException("Invalid account level: $level");
    }
    if (!in_array($period, ['1y', '2y', '3y'], true)) {
      throw new InvalidArgumentException("Invalid period: $period (expected 1y, 2y, 3y)");
    }

    // Free tier: clear plan dates so this looks like a never-paid account.
    if ($level === 0) {
      $stmt = $this->db->prepare(
        "UPDATE users SET acctlevel = 0, plan_start_date = NULL, plan_until_date = NULL, subscription_period = NULL WHERE usernpub = ?"
      );
      if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
      try {
        $stmt->bind_param('s', $this->npub);
        if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
      } finally {
        $stmt->close();
      }
      $this->fetchAccountData();
      return ['planUntilDate' => null, 'level' => 0];
    }

    $today = date('Y-m-d');
    $duration = match ($period) {
      '1y' => '+1 year',
      '2y' => '+2 years',
      '3y' => '+3 years',
    };
    $until = date('Y-m-d', strtotime("{$today} {$duration}"));
    if ($until === false) throw new Exception("date arithmetic failed for period {$period}");

    $stmt = $this->db->prepare(
      "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE usernpub = ?"
    );
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('issss', $level, $today, $until, $period, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return ['planUntilDate' => $until, 'level' => $level];
  }

  /**
   * Admin-extend the user's subscription by N days. Base is current
   * plan_until_date when the plan is active; today when the plan has
   * already expired (treats expired-renewal the same as a fresh add-on).
   * Refuses to operate on accounts that have NEVER had a plan
   * (plan_until_date IS NULL) — admin must use adminSetPlan first.
   *
   * @param int $days 1..3650 (≈10 years)
   * @return string new plan_until_date in YYYY-MM-DD
   */
  public function adminExtendSubscription(int $days): string
  {
    if ($days < 1 || $days > 3650) {
      throw new InvalidArgumentException("days out of range (1..3650): $days");
    }
    $this->fetchAccountData();
    $currentEnd = $this->account['plan_until_date'] ?? null;
    if ($currentEnd === null) {
      throw new InvalidArgumentException('user has no plan to extend — use adminSetPlan first');
    }
    $today = date('Y-m-d');
    // Expired plans renew from today (preserves the "you paid for X more
    // days" semantic without retroactively crediting the lapsed window).
    $base = ($currentEnd < $today) ? $today : $currentEnd;
    $newEnd = date('Y-m-d', strtotime("{$base} +{$days} days"));
    if ($newEnd === false) throw new Exception("date arithmetic failed");

    $stmt = $this->db->prepare("UPDATE users SET plan_until_date = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('ss', $newEnd, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return $newEnd;
  }

  /**
   * Admin-set absolute expiry date. For corrections — the typical action
   * is adminExtendSubscription; this is the escape hatch when the admin
   * needs to override the date directly (refund cases, audit fixes).
   *
   * @param string $date YYYY-MM-DD, must be today or later
   * @return string the persisted plan_until_date
   */
  public function adminSetExpiryDate(string $date): string
  {
    // Strict format validation — strtotime is permissive and would accept
    // 'tomorrow', '+5 days', etc., which is not the admin's intent.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      throw new InvalidArgumentException("date must be YYYY-MM-DD: $date");
    }
    $ts = strtotime($date);
    if ($ts === false) throw new InvalidArgumentException("date unparseable: $date");
    $normalized = date('Y-m-d', $ts);
    if ($normalized !== $date) {
      // e.g. '2025-13-40' would normalize differently.
      throw new InvalidArgumentException("date invalid: $date");
    }
    if ($normalized < date('Y-m-d')) {
      throw new InvalidArgumentException("expiry must be today or later: $date");
    }
    $this->fetchAccountData();
    if (($this->account['plan_until_date'] ?? null) === null) {
      throw new InvalidArgumentException('user has no plan — use adminSetPlan first');
    }

    $stmt = $this->db->prepare("UPDATE users SET plan_until_date = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('ss', $normalized, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return $normalized;
  }

  /**
   * Toggle npub_verified. Marker for "this user has cryptographically
   * proven control of the npub" — usually set by the NIP-05 / DM
   * verification flow; admin override exists for support tickets where
   * the verification path failed but the user is legitimate (or the
   * reverse — flagging a once-verified account for review).
   *
   * @param bool $verified
   * @return void
   * @throws Exception on DB failure
   */
  public function adminSetNpubVerified(bool $verified): void
  {
    $val = $verified ? 1 : 0;
    $stmt = $this->db->prepare("UPDATE users SET npub_verified = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $val, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
      if ($stmt->affected_rows === 0 && !$this->accountExists()) {
        throw new Exception("user not found");
      }
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
  }

  /**
   * Toggle allow_npub_login. When false, the npub login path is
   * blocked for this user — they must use password auth. Doesn't
   * invalidate existing sessions (already-authed devices continue to
   * work); just prevents NEW npub-based logins. Pair with a password
   * reset + killSessions if you also need to kick everyone.
   *
   * @param bool $allow
   * @return void
   * @throws Exception on DB failure
   */
  public function adminSetAllowNpubLogin(bool $allow): void
  {
    $val = $allow ? 1 : 0;
    $stmt = $this->db->prepare("UPDATE users SET allow_npub_login = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $val, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
      if ($stmt->affected_rows === 0 && !$this->accountExists()) {
        throw new Exception("user not found");
      }
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
  }

  /**
   * Set the account's add-on storage allowance (bytes), added on top of the
   * plan tier's base limit by getStorageSpaceLimit(). Absolute SET, not an
   * increment, so it's idempotent. Pass 0 to remove the add-on.
   *
   * Deliberately NOT admin-scoped: this is the single primitive for changing
   * add-on storage. Today the admin tool calls it; a future paid "buy add-on
   * storage" flow can reuse it directly (e.g. setAddonStorage(current + bought))
   * without duplicating the persistence path.
   *
   * @param int $bytes non-negative add-on allowance in bytes
   * @return int the persisted add-on byte value
   * @throws InvalidArgumentException on a negative value
   * @throws Exception on DB failure / unknown user
   */
  public function setAddonStorage(int $bytes): int
  {
    if ($bytes < 0) {
      throw new InvalidArgumentException("addon storage must be >= 0: $bytes");
    }
    $stmt = $this->db->prepare("UPDATE users SET addon_storage = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $bytes, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
      if ($stmt->affected_rows === 0 && !$this->accountExists()) {
        throw new Exception("user not found");
      }
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return $bytes;
  }

  /**
   * Generate a strong random password, persist both legacy hashes, and
   * return the plaintext to the caller exactly ONCE. The plaintext is
   * never logged or stored anywhere else — the caller (admin route) is
   * responsible for surfacing it to the human admin and discarding.
   *
   * Hashing matches createAccount(): bcrypt via password_hash() PLUS the
   * pbkdf2 variant so legacy auth paths continue to work.
   *
   * 18 random bytes → 24 base64url chars; well above any practical brute
   * force, and short enough to read off a screen and type once.
   *
   * @return string plaintext password (return to admin, never persist plaintext)
   */
  public function adminResetPassword(): string
  {
    $bytes = random_bytes(18);
    $password = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pbkdf2 = hashPasswordPBKDF2($password);

    $stmt = $this->db->prepare("UPDATE users SET password = ?, pbkdf2_password = ? WHERE usernpub = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('sss', $hash, $pbkdf2, $this->npub);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
      if ($stmt->affected_rows === 0) {
        throw new Exception("user not found");
      }
    } finally {
      $stmt->close();
    }
    return $password;
  }
}

// Helper function to find npub by referral code
/**
 * Finds the npub (public key) associated with a given referral code.
 *
 * @param mysqli $db The MySQLi database connection object.
 * @param string $referralCode The referral code to search for.
 * @return string The npub (public key) associated with the referral code.
 */
function findNpubByReferralCode(mysqli $db, string $referralCode): string
{
  $sql = "SELECT usernpub FROM users WHERE referral_code = ? AND acctlevel IN (1,2,10) AND plan_until_date > NOW()";
  $stmt = $db->prepare($sql);

  if (!$stmt) {
    throw new Exception("Error preparing statement: " . $db->error);
  }

  try {
    // Upper case referral code
    $referralCode = strtoupper($referralCode);
    if (!$stmt->bind_param('s', $referralCode)) {
      throw new Exception("Error binding parameters: " . $stmt->error);
    }

    if (!$stmt->execute()) {
      throw new Exception("Error executing statement: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
      throw new Exception("Error getting result: " . $stmt->error);
    }

    $npub = $result->fetch_assoc()['usernpub'] ?? '';
  } finally {
    $stmt->close();
  }

  return $npub;
}
