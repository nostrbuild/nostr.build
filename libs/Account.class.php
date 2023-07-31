<?php

/*
Main class to work with accounts
For reference:
desc users;
+-----------------+--------------+------+-----+-------------------+-------------------+
| Field           | Type         | Null | Key | Default           | Extra             |
+-----------------+--------------+------+-----+-------------------+-------------------+
| id              | int          | NO   | PRI | NULL              | auto_increment    |
| usernpub        | varchar(70)  | NO   | UNI | NULL              |                   |
| password        | varchar(255) | NO   |     | NULL              |                   |
| nym             | varchar(64)  | NO   |     | NULL              |                   |
| wallet          | varchar(255) | NO   |     | NULL              |                   |
| ppic            | varchar(255) | NO   |     | NULL              |                   |
| paid            | varchar(255) | NO   |     | NULL              |                   |
| acctlevel       | int          | NO   |     | NULL              |                   |
| flag            | varchar(10)  | NO   |     | NULL              |                   |
| created_at      | datetime     | YES  |     | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| accflags        | json         | YES  |     | NULL              |                   |
| plan_start_date | datetime     | YES  |     | NULL              |                   |
+-----------------+--------------+------+-----+-------------------+-------------------+

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

class Account
{
  private string $npub;
  private array $account;
  private mysqli $db;
  private const STORAGE_LIMITS = [
    99 => PHP_INT_MAX, // Unlimited
    89 => 100 * 1024, // 100MiB
    5 => 5 * 1024 * 1024 * 1024, // 5GiB
    4 => 0, // No Storage, consider upgrading
    3 => 5 * 1024 * 1024 * 1024, // 5GiB
    2 => 10 * 1024 * 1024 * 1024, // 10GiB
    1 => 20 * 1024 * 1024 * 1024, // 20GiB
    0 => 0 // No Storage, consider upgrading
  ];


  public function __construct(string $npub, mysqli $db)
  {
    $this->npub = $npub;
    $this->db = $db;
    // Populate account data
    $this->fetchAccountData();
  }

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

      $this->account = $result->fetch_assoc();
      if (!$this->account) {
        // Handle no matching record found
        throw new Exception("No matching record found for npub: " . $this->npub);
      }
    } finally {
      $stmt->close();
    }
  }

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

  public function getNpub(): string
  {
    return $this->npub;
  }

  public function getAccount(): array
  {
    return $this->account;
  }

  public function getAccountLevel(): AccountLevel
  {
    return AccountLevel::from($this->account['acctlevel'] ?? AccountLevel::Invalid);
  }

  /*
  // Example usage:
  try {
      $db = new mysqli("localhost", "username", "password", "database");
      $npub = "exampleUser";
      $password = "examplePassword";
      $level = AccountLevel::Creator; // or some valid account level
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
  public function createAccount(string $password, int $level = 0): void
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

    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash

    $sql = "INSERT INTO users (usernpub, password, acctlevel) VALUES (?, ?, ?)";
    $stmt = $this->db->prepare($sql);

    if (!$stmt) {
      throw new Exception("Error preparing statement: " . $this->db->error);
    }

    try {
      $stmt->bind_param('ssi', $this->npub, $hashed_password, $level);
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
  }

  private function accountExists(): bool
  {
    return !empty($this->account);
  }

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
  public function updateAccount(
    string $password = null,
    string $nym = null,
    string $wallet = null,
    string $ppic = null,
    string $paid = null,
    int $acctlevel = null,
    string $flag = null,
    array $accflags = null,
    string $plan_start_date = null
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
    ];

    $sql = "UPDATE users SET ";
    $params = [];
    $types = '';

    foreach ($updates as $field => $value) {
      if ($value !== null) {
        $sql .= "$field = ?, ";
        $params[] = $value;
        $types .= is_int($value) ? 'i' : 's';
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
  }

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

  public function isAccountValid(): bool
  {
    return $this->getAccountLevel() !== AccountLevel::Invalid;
  }

  public function isAccountVerified(): bool
  {
    return $this->getAccountLevel() >= AccountLevel::Unverified;
  }

  public function isAccountInvalid(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Invalid;
  }

  public function isAccountUnverified(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Unverified;
  }

  public function isAccountCreator(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Creator;
  }

  public function isAccountProfessional(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Professional;
  }

  public function isAccountViewer(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Viewer;
  }

  public function isAccountStarter(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Starter;
  }

  public function isAccountModerator(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Moderator;
  }

  public function isAccountAdmin(): bool
  {
    return $this->getAccountLevel() === AccountLevel::Admin;
  }

  public function getRemainingStorageSpace(): int
  {
    $accountLevel = $this->account['acctlevel'];
    $limit = self::STORAGE_LIMITS[$accountLevel] ?? 0; // Default to 0 if level not found

    $usedSpace = $this->fetchAccountSpaceConsumption();

    return $limit - $usedSpace;
  }

  public function hasSufficientStorageSpace(int $fileSize): bool
  {
    $remainingSpace = $this->getRemainingStorageSpace();

    // If there's enough space for the file, including unlimited space for Admin
    return $fileSize <= $remainingSpace;
  }
}
