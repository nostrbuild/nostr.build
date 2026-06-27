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
class DuplicateEmailException extends Exception {}
// Thrown when an operation would remove an account's LAST sign-in method (the
// ≥1-authenticator invariant): e.g. removing the email from an account with no
// usable Nostr-key login, or disabling Nostr login on an account with no
// verified email + password.
class LastAuthenticatorException extends Exception {}
// Thrown when claiming a Nostr key onto an account that already has one (claiming
// is first-key-only; changing an existing key is admin npub-rotation).
class NpubAlreadySetException extends Exception {}

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
  private string $lookupColumn = 'usernpub';
  private string $lookupValue = '';
  private string $uuid = '';
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
  public function __construct(string $npub, mysqli $db, ?string $uuid = null)
  {
    $this->npub = trim($npub);
    $this->db = $db;
    $this->uploadsData = new UploadsData($db);
    if ($this->npub === '' && $uuid !== null && trim($uuid) !== '') {
      $this->lookupColumn = 'uuid_id';
      $this->lookupValue = trim($uuid);
    } else {
      $this->lookupColumn = 'usernpub';
      $this->lookupValue = $this->npub;
    }
    // Populate account data (also sets $this->uuid + backfills $this->npub).
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
    $account = new self('', $db, $uuid);
    return $account->accountExists() ? $account : null;
  }

  /**
   * Build an Account from a (verified) email address. Resolves email -> uuid_id,
   * then constructs through fromUuid so all existing (npub-keyed) Account logic
   * keeps working. An unverified address is never stored (setEmail sets
   * email_verified=1 atomically), so a hit here is always a verified credential.
   * Returns null when no account owns that email. Used by the accounts Worker
   * for email login + password reset.
   *
   * @param string $email
   * @param mysqli $db
   * @return self|null
   */
  public static function fromEmail(string $email, mysqli $db): ?self
  {
    $email = strtolower(trim($email));
    if ($email === '') {
      return null;
    }
    $stmt = $db->prepare("SELECT uuid_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row) || empty($row['uuid_id'])) {
      return null;
    }
    return self::fromUuid((string) $row['uuid_id'], $db);
  }

  /**
   * Summary of fetchAccountData
   * @throws \Exception
   * @return void
   */
  private function fetchAccountData(): void
  {
    $sql = "SELECT * FROM users WHERE {$this->lookupColumn} = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      // You can log or handle this error as needed
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param('s', $this->lookupValue)) {
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
      $this->uuid = (string) ($this->account['uuid_id'] ?? '');
      if ($this->npub === '') {
        $this->npub = (string) ($this->account['usernpub'] ?? '');
      }
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
    $sql = "SELECT SUM(file_size) AS total FROM users_images WHERE user_uuid = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      // You can log or handle this error as needed
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    $uuid = $this->getAccountUuid();
    try {
      if (!$stmt->bind_param('s', $uuid)) {
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
   * Create an npub-less ("email") account: email + password, no Nostr key. The
   * Worker has already proven inbox control (single-use signup magic-link), so
   * email_verified = 1. usernpub is left NULL and npub login is off (the account
   * has no key); a key can be claimed later (add-npub). Loads the inserted row so
   * $this->uuid is set for the caller. Throws DuplicateEmailException (errno 1062)
   * when the address is already in use.
   *
   * @throws DuplicateEmailException
   * @throws InvalidAccountLevelException
   * @throws Exception
   */
  public function createEmailAccount(string $email, string $password, ?string $name = null, int $level = 0): void
  {
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
      throw new Exception('email is required');
    }
    try {
      AccountLevel::from($level);
    } catch (ValueError $e) {
      throw new InvalidAccountLevelException("Invalid account level: $level");
    }

    $hashed_password = password_hash(trim($password), PASSWORD_DEFAULT);
    $pbkdf2_hashed_password = hashPasswordPBKDF2(trim($password));
    $referralCode = generateUniqueCode();
    $nym = ($name !== null && trim($name) !== '') ? trim($name) : null;

    // usernpub NULL (no key); email_verified = 1; npub_verified / allow_npub_login = 0.
    $sql = "INSERT INTO users (usernpub, email, email_verified, password, pbkdf2_password, acctlevel, npub_verified, allow_npub_login, referral_code, nym) VALUES (NULL, ?, 1, ?, ?, ?, 0, 0, ?, ?)";
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      $stmt->bind_param('sssiss', $normalized, $hashed_password, $pbkdf2_hashed_password, $level, $referralCode, $nym);
      if (!$stmt->execute()) {
        if ($this->db->errno == 1062) {
          throw new DuplicateEmailException("Email $normalized already in use");
        }
        throw new Exception("Database error: " . $this->db->error);
      }
    } finally {
      $stmt->close();
    }

    // Load the just-inserted row by email so $this->uuid (+ the rest of the
    // account data) is populated before the caller reads it back.
    $this->lookupColumn = 'email';
    $this->lookupValue = $normalized;
    $this->fetchAccountData();
  }

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

    // Load the just-inserted row so $this->uuid (DB-auto-populated on INSERT) is
    // set BEFORE any uuid-keyed write below — the Nostr-profile import's UPDATE
    // keys on uuid_id, which is '' until the row is fetched back.
    $this->fetchAccountData();

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
    $sql = "UPDATE users SET legal_accepted_at = NOW(), legal_version = ? WHERE uuid_id = ?";
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing legal-acceptance statement: " . $this->db->error);
    }
    try {
      $stmt->bind_param('ss', $version, $this->uuid);
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
    $sql .= " WHERE uuid_id = ?";
    $params[] = $this->uuid;
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
    $sql = "DELETE FROM users WHERE uuid_id = ?";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      if (!$stmt->bind_param('s', $this->uuid)) {
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

  /** Admin-termination category ('user'|'gdpr'|'dmca'|'ban'|'legal'), or null
   *  for self-service / none. For the admin audit + snapshot only — never shown
   *  to the user (the 'admin'/'forced' status alone drives their banner). */
  public function getDeletionCategory(): ?string
  {
    return $this->account['deletion_category'] ?? null;
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
    // Never overwrite an existing schedule — incl. an admin termination
    // ('admin'/'forced'): a self-service request must not downgrade a ban/legal
    // takedown to a cancelable self-service deletion.
    if (($this->account['deletion_status'] ?? 'none') !== 'none') {
      return 'noop-pending';
    }
    $now = date('Y-m-d H:i:s');
    $deleteAfter = date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));
    $stmt = $this->db->prepare(
      "UPDATE users SET deletion_status = 'pending', deletion_requested_at = ?, delete_after = ? WHERE uuid_id = ?"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('sss', $now, $deleteAfter, $this->uuid)) {
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
   * ADMIN-initiated termination. Unlike requestDeletion() (self-service,
   * expired-only, the user's own request), this terminates an account for a
   * ban, GDPR erasure, DMCA, voluntary closure on the user's behalf, or a legal
   * order — so it does NOT check isExpired() (active accounts can be terminated).
   * The status encodes the user-facing policy: 'admin' = APPEALABLE (user
   * request / GDPR / DMCA → the user is shown a contact-us window), 'forced' =
   * for-cause / compelled (ban / legal → no save path). $windowDays 0 = no
   * cooldown (the workflow wipes as soon as it wakes). Records the category, a
   * free-text reason/reference, and the acting admin for the audit + legal
   * record. Returns the resulting status ('admin' | 'forced').
   * @throws \Exception on DB failure
   */
  public function adminScheduleDeletion(int $windowDays, string $category, string $reason, string $actor): string
  {
    $this->fetchAccountData();
    $appealable = in_array($category, ['user', 'gdpr', 'dmca'], true);
    $status = $appealable ? 'admin' : 'forced';
    $now = date('Y-m-d H:i:s');
    $deleteAfter = $windowDays <= 0 ? $now : date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));
    $stmt = $this->db->prepare(
      "UPDATE users SET deletion_status = ?, deletion_requested_at = ?, delete_after = ?,
         deletion_category = ?, deletion_reason = ?, deletion_actor = ? WHERE uuid_id = ?"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('sssssss', $status, $now, $deleteAfter, $category, $reason, $actor, $this->uuid)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }
      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    return $status;
  }

  /**
   * AUTOMATED long-inactivity termination — the Sunday sweep's per-account
   * setter. Behaves like a self-service deletion (status 'pending' → the user's
   * renew/downgrade still clears it via cancelDeletion, media stays live for the
   * window, /deletion-state keeps the expired-only guard), but is tagged
   * category='inactivity' + actor='system' so the admin views + audit can tell
   * it apart from a user-initiated 'pending' and from an admin 'admin'/'forced'
   * termination.
   *
   * The flip is ATOMIC, IDEMPOTENT, and RACE-SAFE: a single conditional UPDATE
   * only touches a row that is STILL eligible (deletion_status='none', not staff,
   * expired by more than $minExpiredDays). So a concurrent renewal, a prior
   * sweep pass, a manual admin termination, or a re-run after a retry can never
   * clobber an existing schedule or re-schedule an ineligible account.
   * `scheduled` is true only when this call did the flip (affected_rows === 1) —
   * the caller DMs/audits only then.
   *
   * @return array{scheduled: bool, status: string, deleteAfter: ?int}
   * @throws \Exception on DB failure
   */
  public function scheduleInactivityDeletion(int $windowDays = 30, int $minExpiredDays = 730): array
  {
    $this->fetchAccountData();
    $windowDays = max(0, $windowDays);
    $minExpiredDays = max(1, $minExpiredDays);
    $now = date('Y-m-d H:i:s');
    $deleteAfter = date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));
    // $windowDays/$minExpiredDays are int-typed, so the inline INTERVAL is
    // injection-safe (MySQL can't bind an INTERVAL operand to a placeholder).
    $stmt = $this->db->prepare(
      "UPDATE users
          SET deletion_status = 'pending', deletion_category = 'inactivity',
              deletion_reason = 'Inactive (expired) for over 2 years — automated sweep',
              deletion_actor = 'system', deletion_requested_at = ?, delete_after = ?
        WHERE uuid_id = ?
          AND deletion_status = 'none'
          AND acctlevel NOT IN (89, 99)
          AND plan_until_date IS NOT NULL
          AND plan_until_date < DATE_SUB(NOW(), INTERVAL {$minExpiredDays} DAY)"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('sss', $now, $deleteAfter, $this->uuid)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }
      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
      $scheduled = $stmt->affected_rows === 1;
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    $after = $this->getDeletionDeleteAfter();
    return [
      'scheduled' => $scheduled,
      'status' => $this->getDeletionStatus(),
      'deleteAfter' => $after !== null ? strtotime($after) : null,
    ];
  }

  /**
   * Clear a scheduled deletion (back to normal). Callers:
   *   - setPlan (renewal/upgrade): force=false — clears ONLY a self-service
   *     'pending' deletion. A renewal must NEVER undo an admin termination
   *     (a ban / court order must not be cancelable by a payment).
   *   - the admin override (legal hold lifted / appeal granted / support):
   *     force=true — clears any status ('pending' | 'admin' | 'forced').
   * Idempotent + safe when nothing is scheduled. Uses its own UPDATE because
   * updateAccount() skips null-valued fields (it can't reset to NULL).
   * @throws \Exception on DB failure
   */
  public function cancelDeletion(bool $force = false): void
  {
    if (!$force && ($this->account['deletion_status'] ?? 'none') !== 'pending') {
      return;
    }
    $stmt = $this->db->prepare(
      "UPDATE users SET deletion_status = 'none', deletion_requested_at = NULL, delete_after = NULL,
         deletion_category = NULL, deletion_reason = NULL, deletion_actor = NULL WHERE uuid_id = ?"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('s', $this->uuid)) {
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
    $this->account['deletion_category'] = null;
    $this->account['deletion_reason'] = null;
    $this->account['deletion_actor'] = null;
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
         deletion_status = 'none', deletion_requested_at = NULL, delete_after = NULL,
         deletion_category = NULL, deletion_reason = NULL, deletion_actor = NULL
       WHERE uuid_id = ?"
    );
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    try {
      if (!$stmt->bind_param('s', $this->uuid)) {
        throw new Exception("Error binding parameters: " . $stmt->error);
      }
      if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
      }
    } finally {
      $stmt->close();
    }
    // The user is wiped to a blank shell: drop their published-note records and
    // their note↔image links, keyed by the stable uuid. (Media rows are removed
    // before this runs.) The links are deleted explicitly — the app owns the
    // cascade, not an FK — so this is correct even with no FK on the table.
    $uuid = $this->getAccountUuid();
    if ($uuid !== null && $uuid !== '') {
      $imageStmt = $this->db->prepare("DELETE FROM users_nostr_images WHERE user_uuid = ?");
      if (!$imageStmt) {
        throw new Exception("Error preparing users_nostr_images delete: " . $this->db->error);
      }
      try {
        $imageStmt->bind_param('s', $uuid);
        if (!$imageStmt->execute()) {
          throw new Exception("Error deleting users_nostr_images rows: " . $imageStmt->error);
        }
      } finally {
        $imageStmt->close();
      }
      $noteStmt = $this->db->prepare("DELETE FROM users_nostr_notes WHERE user_uuid = ?");
      if (!$noteStmt) {
        throw new Exception("Error preparing users_nostr_notes delete: " . $this->db->error);
      }
      try {
        $noteStmt->bind_param('s', $uuid);
        if (!$noteStmt->execute()) {
          throw new Exception("Error deleting users_nostr_notes rows: " . $noteStmt->error);
        }
      } finally {
        $noteStmt->close();
      }
    }
    $this->fetchAccountData();
    // Keep Blossom in sync with the now-free tier (same as setPlan does).
    // Best-effort: the terminal DB reset above has already committed and is the
    // source of truth. A Blossom transport failure must NOT throw out of this
    // method — the media rows were already deleted by finalize-deletion BEFORE
    // this runs, so a throw here would leave the account un-finalizable (bytes +
    // rows gone, yet still acctlevel>0 / deletion_status='pending' with no live
    // workflow to retry). Blossom reconciles on the next sync.
    try {
      $this->blossomFrontEndAPI->updateAccount($this->npub, $this->getAccountInfo());
    } catch (\Throwable $e) {
      error_log('wipeForDeletion: Blossom sync failed for ' . $this->npub . ': ' . $e->getMessage());
    }
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
   * Legal-hold lockout timestamp (users.locked_at), in unix seconds, or null
   * when the account is not under a hold. Set the instant CSAM is reported to
   * freeze the account for criminal-evidence preservation; the login routes
   * reject a locked account with 423 and the accounts Worker keeps it frozen.
   * @return int|null
   */
  public function getLockedAt(): ?int
  {
    // Guard the pre-ALTER window explicitly: if the column doesn't exist yet,
    // `SELECT *` simply has no key and this returns null — but make that
    // unambiguous rather than a silent "not locked" if the row is partial.
    if (!array_key_exists('locked_at', $this->account)) {
      return null;
    }
    $raw = $this->account['locked_at'];
    if ($raw === null || $raw === '' || $raw === '0000-00-00 00:00:00') {
      return null;
    }
    $ts = strtotime((string) $raw);
    return $ts === false ? null : $ts;
  }

  /** True when the account is under a legal-hold lock. */
  public function isLocked(): bool
  {
    return $this->getLockedAt() !== null;
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
  /**
   * Link a now-subscribed user's existing FREE uploads to their stable uuid.
   * Free/anon uploads land in uploads_data/upload_attempts keyed by npub with a
   * NULL user_uuid; once that npub owns a paid account we stamp the uuid on the
   * prior rows. Best-effort + idempotent (fills NULLs only) — a failure here must
   * never block plan activation.
   */
  private function linkFreeUploadsToUuid(): void
  {
    $uuid = $this->getAccountUuid();
    if ($uuid === null || $uuid === '' || $this->npub === '') {
      return;
    }
    foreach (['uploads_data', 'upload_attempts'] as $table) {
      try {
        $stmt = $this->db->prepare("UPDATE {$table} SET user_uuid = ? WHERE usernpub = ? AND user_uuid IS NULL");
        if (!$stmt) {
          continue;
        }
        $stmt->bind_param('ss', $uuid, $this->npub);
        $stmt->execute();
        $stmt->close();
      } catch (\Throwable $e) {
        error_log("linkFreeUploadsToUuid({$table}) failed for " . $this->npub . ': ' . $e->getMessage());
      }
    }
  }

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
    // date must be a sane, bounded value. Self-contained branch so the normal
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
      // A downgrade converts the FULL remaining value of the (pricier) current
      // tier into bonus days at the (cheaper) target tier's daily rate, so a
      // downgrade from a high tier to a cheap one legitimately produces a very
      // long runway: Advanced->Purist at the renewal-window edge converts to
      // ~9.55 years, and a 3y term + bonus easily clears 4 years. Bound at
      // 10 years (+60 days slack) so the genuine maximum is honored in full
      // rather than rejected as out-of-bounds; the app holds itself to a
      // stricter 10-year flat (MAX_DOWNGRADE_TERM_DAYS) so it never sends a date
      // this rejects.
      $maxDate = date('Y-m-d', strtotime($today . ' +10 years +60 days'));
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
      $stmt = $this->db->prepare("UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE uuid_id = ?");
      if (!$stmt) {
        throw new Exception("Error preparing statement: " . $this->db->error);
      }
      try {
        if (!$stmt->bind_param('issss', $planLevel, $overrideStart, $overrideEnd, $period, $this->uuid)) {
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
      $sql = "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE uuid_id = ?";
    } else {
      $sql = "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? " .
        "WHERE uuid_id = ? AND plan_until_date < DATE_ADD(CURDATE(), INTERVAL {$safeRenewDays} DAY)";
    }

    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    $status = 'applied';
    try {
      error_log("Account data: " . print_r($this->account, true));
      error_log("Plan start date: $planStartDate, Plan end date: $planEndDate" . PHP_EOL);

      if (!$stmt->bind_param('issss', $planLevel, $planStartDate, $planEndDate, $period, $this->uuid)) {
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

        // Newly-paid account: link any of this npub's prior free uploads to the uuid.
        $this->linkFreeUploadsToUuid();

        // Also push the uuid to blossom.band so a prior free-tier blossom user
        // (no uuid) gets tied to this account identity — keeps their subdomain
        // attached across any future npub rotation. Best-effort + idempotent
        // (DB-only upstream, mints nothing); never block plan activation.
        $blossomUuid = $this->getAccountUuid();
        if ($blossomUuid !== null && $blossomUuid !== '' && $this->npub !== '') {
          try {
            $this->blossomFrontEndAPI->linkAccountUuid($this->npub, $blossomUuid);
          } catch (\Throwable $e) {
            error_log('linkAccountUuid(blossom) failed for ' . $this->npub . ': ' . $e->getMessage());
          }
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
    // ≥1-authenticator invariant: disabling Nostr login is only allowed when the
    // account still has a usable email sign-in (verified email + password). Else
    // the user would lock themselves out.
    if ($allow === false && !$this->hasEmailAuthenticator()) {
      throw new LastAuthenticatorException('Cannot disable Nostr login without an email sign-in');
    }
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
    // email is PII: never include it in this shared payload. getAccountInfo() is
    // also returned by the cross-user GET /blossom/account/{npub} route and pushed
    // to blossom-band by blossomFrontEndAPI->updateAccount(). Self/admin read the
    // email via getEmail()/isEmailVerified() instead.
    unset($accountInfo['email']);
    unset($accountInfo['email_verified']);
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

  /**
   * The account's email (null when none set). Deliberately NOT in getAccountInfo()
   * — that payload is shared with the cross-user /blossom/account route and the
   * blossom sync. Read email only in self/admin contexts.
   */
  public function getEmail(): ?string
  {
    return $this->account['email'] ?? null;
  }

  /** Whether the account's email has been verified. */
  public function isEmailVerified(): bool
  {
    return (bool) ($this->account['email_verified'] ?? 0);
  }

  /** Whether a login password is set (the hash itself is never exposed). */
  public function hasPassword(): bool
  {
    return !empty($this->account['password']);
  }

  /** A usable email sign-in method: a verified address + a password. */
  public function hasEmailAuthenticator(): bool
  {
    return $this->getEmail() !== null && $this->isEmailVerified() && $this->hasPassword();
  }

  /** A usable Nostr-key sign-in method: an npub with key-login enabled. */
  public function hasNpubAuthenticator(): bool
  {
    return !empty($this->account['usernpub']) && (bool) ($this->account['allow_npub_login'] ?? 0);
  }

  /**
   * Remove the email credential (the ≥1-authenticator invariant): allowed only
   * when the account can still sign in by its Nostr key. Clears email +
   * email_verified in one uuid-keyed write.
   *
   * @throws LastAuthenticatorException when email is the only sign-in method.
   * @throws Exception
   */
  public function removeEmail(): void
  {
    if ($this->uuid === '') {
      throw new Exception("removeEmail: account not loaded (missing uuid)");
    }
    if (!$this->hasNpubAuthenticator()) {
      throw new LastAuthenticatorException('Cannot remove the only sign-in method');
    }
    $stmt = $this->db->prepare("UPDATE users SET email = NULL, email_verified = 0 WHERE uuid_id = ?");
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    $stmt->bind_param('s', $this->uuid);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new Exception("Database error removing email: " . $this->db->error);
    }
    $stmt->close();
    $this->account['email'] = null;
    $this->account['email_verified'] = 0;
  }

  /**
   * Set + verify the account's email in one uuid-keyed write. The Worker has
   * already proven control of the inbox (single-use magic-link token), so this
   * sets email_verified = 1 and atomically swaps any prior address.
   *
   * Email is stored lowercase-normalized (the Worker normalizes too; this is
   * defense in depth). The UNIQUE index raises errno 1062 when another account
   * already owns the address — surfaced as DuplicateEmailException for a 409.
   *
   * @throws DuplicateEmailException when the address is taken by another account.
   */
  public function setEmail(string $email): void
  {
    if ($this->uuid === '') {
      throw new Exception("setEmail: account not loaded (missing uuid)");
    }
    $normalized = strtolower(trim($email));
    $stmt = $this->db->prepare("UPDATE users SET email = ?, email_verified = 1 WHERE uuid_id = ?");
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    $stmt->bind_param('ss', $normalized, $this->uuid);
    if (!$stmt->execute()) {
      $errno = $this->db->errno;
      $error = $this->db->error;
      $stmt->close();
      if ($errno == 1062) { // Duplicate entry: another account owns this email.
        throw new DuplicateEmailException("Email already in use");
      }
      throw new Exception("setEmail execute failed: " . $error);
    }
    $stmt->close();
    // Keep the in-memory row coherent for any subsequent getter in this request.
    $this->account['email'] = $normalized;
    $this->account['email_verified'] = 1;
  }

  /**
   * Whether $email is already taken by a DIFFERENT account. Powers the Worker's
   * enumeration-safe availability probe (skip the magic-link send when taken);
   * the canonical uniqueness guarantee is still the UNIQUE index at write time.
   */
  public function emailTakenByOther(string $email): bool
  {
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
      return false;
    }
    $stmt = $this->db->prepare("SELECT 1 FROM users WHERE email = ? AND uuid_id <> ? LIMIT 1");
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    $stmt->bind_param('ss', $normalized, $this->uuid);
    $stmt->execute();
    $taken = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $taken;
  }

  /**
   * Claim a Nostr key for a key-less ("email") account: attach $npub to a row
   * that currently has none, mark it verified, and enable npub login. The Worker
   * has already proven ownership of $npub (a NIP-07 signature OR a DM round-trip),
   * so we trust it here and bind it under the account's stable uuid.
   *
   * First-key-only: refuses (NpubAlreadySetException) when the account already
   * has a key — changing an existing key is admin npub-rotation, not a self-serve
   * claim. The usernpub UNIQUE index raises errno 1062 when another account
   * already owns $npub — surfaced as DuplicateUserException for a 409.
   *
   * Pushes the now-keyed profile to the Blossom API exactly like verifyNpub():
   * an email-only account never reaches that push (no key), so claiming is the
   * moment a Blossom-side npub record first becomes meaningful. No new Blossom
   * code — the normal profile push carries it.
   *
   * @throws NpubAlreadySetException when the account already has a key.
   * @throws DuplicateUserException when $npub is attached to another account.
   * @throws Exception
   */
  public function claimNpub(string $npub): void
  {
    if ($this->uuid === '') {
      throw new Exception("claimNpub: account not loaded (missing uuid)");
    }
    $npub = trim($npub);
    if (strpos($npub, 'npub1') !== 0 || strlen($npub) < 60 || strlen($npub) > 100) {
      throw new Exception("claimNpub: invalid npub");
    }
    if (!empty($this->account['usernpub'])) {
      throw new NpubAlreadySetException('Account already has a Nostr key');
    }

    $stmt = $this->db->prepare("UPDATE users SET usernpub = ?, npub_verified = 1, allow_npub_login = 1 WHERE uuid_id = ?");
    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }
    $stmt->bind_param('ss', $npub, $this->uuid);
    if (!$stmt->execute()) {
      $errno = $this->db->errno;
      $error = $this->db->error;
      $stmt->close();
      if ($errno == 1062) { // usernpub UNIQUE: the key is attached elsewhere.
        throw new DuplicateUserException("Nostr key already in use");
      }
      throw new Exception("claimNpub execute failed: " . $error);
    }
    $stmt->close();

    // Keep the in-memory row + npub property coherent so getAccountInfo() and the
    // Blossom push below (and any later getter this request) see the new key.
    $this->account['usernpub'] = $npub;
    $this->account['npub_verified'] = 1;
    $this->account['allow_npub_login'] = 1;
    $this->npub = $npub;
    $this->setSessionParameters();

    // Normal profile push to Blossom — now that a key exists, the npub record is
    // meaningful (verifyNpub() does the identical push on its own path).
    $newData = $this->getAccountInfo();
    $this->blossomFrontEndAPI->updateAccount($this->npub, $newData);
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
        "UPDATE users SET acctlevel = 0, plan_start_date = NULL, plan_until_date = NULL, subscription_period = NULL WHERE uuid_id = ?"
      );
      if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
      try {
        $stmt->bind_param('s', $this->uuid);
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
      "UPDATE users SET acctlevel = ?, plan_start_date = ?, plan_until_date = ?, subscription_period = ? WHERE uuid_id = ?"
    );
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('issss', $level, $today, $until, $period, $this->uuid);
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

    $stmt = $this->db->prepare("UPDATE users SET plan_until_date = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('ss', $newEnd, $this->uuid);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    // Reactivation cancels a pending self-service deletion — exactly as setPlan
    // does (the user's only self-service exit is renew/upgrade; an admin grant of
    // more time is the same "this account is staying" signal). Without this, a
    // now-active account keeps deletion_status='pending' and the user is walled
    // to the deletion-pending screen until someone separately clicks cancel.
    if (($this->account['deletion_status'] ?? 'none') === 'pending') {
      $this->cancelDeletion();
    }
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

    $stmt = $this->db->prepare("UPDATE users SET plan_until_date = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('ss', $normalized, $this->uuid);
      if (!$stmt->execute()) throw new Exception("execute failed: " . $stmt->error);
    } finally {
      $stmt->close();
    }
    $this->fetchAccountData();
    // Reactivation cancels a pending deletion (see adminExtendSubscription).
    if (($this->account['deletion_status'] ?? 'none') === 'pending') {
      $this->cancelDeletion();
    }
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
    $stmt = $this->db->prepare("UPDATE users SET npub_verified = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $val, $this->uuid);
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
    $stmt = $this->db->prepare("UPDATE users SET allow_npub_login = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $val, $this->uuid);
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
    $stmt = $this->db->prepare("UPDATE users SET addon_storage = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('is', $bytes, $this->uuid);
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

    $stmt = $this->db->prepare("UPDATE users SET password = ?, pbkdf2_password = ? WHERE uuid_id = ?");
    if (!$stmt) throw new Exception("prepare failed: " . $this->db->error);
    try {
      $stmt->bind_param('sss', $hash, $pbkdf2, $this->uuid);
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
