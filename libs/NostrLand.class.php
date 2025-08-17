<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';



class NostrLand
{
  private $account; // Instance of Account class
  private $bech32;
  private $apiUrl;
  private $apiKey;
  private $idempotentPrefix;

  public function __construct(string $npub, mysqli $db)
  {
    $this->account = new Account($npub, $db);
    $this->bech32 = new Bech32();
    $this->apiUrl = $_SERVER['NL_API_URL'];
    $this->apiKey = $_SERVER['NL_API_KEY'];
    $this->idempotentPrefix = $_SERVER['NL_IDEMPOTENT_PREFIX'] ?? 'nostrland_';

    // Throw error if API URL or key is not set
    if (empty($this->apiUrl) || empty($this->apiKey)) {
      throw new \RuntimeException('API URL and key must be set.');
    }

    // basic tracing
    error_log('[NostrLand] init api_host=' . parse_url($this->apiUrl, PHP_URL_HOST));
  }

  /**
   * Prepares a payload for a given user and tier with a specified duration and idempotency.
   *
   * @param string $idempotentId    A unique idempotency identifier to prevent duplicate processing.
   * @param string $bech32npub      The user's Bech32 encoded public key (npub).
   * @param int    $timeSeconds     The duration in seconds to add to the plan
   * @param string $tier            The tier or level for the payload.
   *
   * @return string                 The prepared payload as a string.
   */
  public function preparePayload(string $idempotentId, string $bech32npub, int $timeSeconds, string $tier = 'plus'): string
  {
    // basic tracing
    error_log('[NostrLand] preparePayload start id=' . $idempotentId . ' npub=' . substr($bech32npub, 0, 12) . '… tier=' . $tier . ' time=' . $timeSeconds);
    // Verify that $idempotentId is set and not empty
    if (empty($idempotentId) || strlen($idempotentId) < 5) {
      throw new \InvalidArgumentException('Idempotent ID must be set.');
    }
    // Verify npub starts with npub1
    if (strpos($bech32npub, 'npub1') !== 0) {
      throw new \InvalidArgumentException('Invalid Bech32 npub format.');
    }
    // Verify time is more than 1 day and less than 3 years + 7 days (buffer)
    if ($timeSeconds < 86400 || $timeSeconds > 94608000 + 604800) {
      throw new \InvalidArgumentException('Time must be between 1 day and 3 years + 7 days (buffer).');
    }
    // Verify that tier is 'plus', more may be added in the future
    if ($tier !== 'plus') {
      throw new \InvalidArgumentException('Tier must be "plus".');
    }
    // Prepare the payload
    $hexKey = $this->bech32->convertBech32ToHex($bech32npub);
    $payloadArr = [
      'idempotency_id' => $this->idempotentPrefix . $idempotentId,
      'pubkey' => $hexKey,
      'tier' => $tier,
      'time' => $timeSeconds,
    ];
    // Convert to JSON
    $json = json_encode($payloadArr);
    error_log('[NostrLand] preparePayload ready len=' . strlen((string)$json));
    return $json;
  }
  /**
   * Convert a Bech32 address to a hex key.
   *
   * @param string $bech32Address
   * @return string
   */
  public function npubToHex(string $bech32Address): string
  {
    // basic tracing
    error_log('[NostrLand] npubToHex start npub=' . substr($bech32Address, 0, 12) . '…');
    return $this->bech32->convertBech32ToHex($bech32Address);
  }

  /**
   * Convert a hex key to a Bech32 address.
   *
   * @param string $hexKey
   * @return string
   */
  public function hexToNpub(string $hexKey): string
  {
    // basic tracing
    error_log('[NostrLand] hexToNpub start hex=' . substr($hexKey, 0, 12) . '…');
    return $this->bech32->convertHexToBech32('npub', $hexKey);
  }

  /**
   * Convert end date to seconds from now
   *
   * Supports integer seconds, integer milliseconds, numeric strings, and parsable date strings.
   *
   * @param int|string $endDate
   * @return int
   */
  public function convertEndDateToSeconds(int|string $endDate): int
  {
    $now = time();

    // Numeric input: integer or numeric string
    if (is_numeric($endDate)) {
      $ts = (int)$endDate;
      // If timestamp appears to be in milliseconds, convert to seconds.
      if ($ts >= 1000000000000) { // ~Sat Nov 16 2286; safe cutoff for ms
        $ts = intdiv($ts, 1000);
      }
    } else {
      // Parse string with DateTimeImmutable for predictable timezone handling (UTC)
      try {
        $dt = new \DateTimeImmutable($endDate, new \DateTimeZone('UTC'));
        $ts = $dt->getTimestamp();
      } catch (\Exception $e) {
        throw new \InvalidArgumentException('Invalid end date string: ' . $endDate);
      }
    }

    return max(0, $ts - $now);
  }

  /**
   * Generate idempotency_id based on user id (db numeric ID) and plan_until_date (mysql datetime)
   * @param int $userId
   * @param int|string $planUntilDate
   * @return string
   */
  public function generateIdempotencyId(): string
  {
    $userId = $this->account->getAccountNumericId();
    $planUntilDate = $this->account->getAccountPlanUntilDate();
    // Throw if either is null
    if ($userId === null || $planUntilDate === null) {
      throw new \InvalidArgumentException('Invalid userId or planUntilDate');
    }
    $tz = new \DateTimeZone('UTC');
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $planUntilDate, $tz);
    if ($dt === false) {
      throw new \InvalidArgumentException('Invalid planUntilDate (expected MySQL DATETIME): ' . $planUntilDate);
    }

    $canonical = $dt->format('YmdHis');
    return sprintf('%d-%s', $userId, $canonical);
  }

  /**
   * Convert days to seconds
   *
   * @param int $days
   * @return int
   */
  public function convertDaysToSeconds(int $days): int
  {
    return $days * 86400;
  }

  /**
   * Method to reliably submit PUT call to the API with the JSON payload, get the response, and handle errors.
   *
   * @param string $payload
   * @return array
   */
  public function submitPayload(string $payload): array
  {
    // basic tracing
    error_log('[NostrLand] submitPayload send url=' . $this->apiUrl . ' len=' . strlen((string)$payload));

    $ch = curl_init($this->apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'X-API-Key: ' . $this->apiKey,
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log('[NostrLand] submitPayload response http=' . $httpCode . ' len=' . (is_string($response) ? strlen($response) : 0));

    if ($httpCode !== 200) {
      error_log('[NostrLand] submitPayload failed http=' . $httpCode);
      throw new \RuntimeException('Failed to submit payload: ' . $response);
    }

    /**
     * Expected return value from the API:
     * {
     * "id": "<UUID>", // Remains same with idempotent_id
     * "request": {
     *   "partner": "nostrbuild", // Partner name
     *   "tier": "plus", // Tier
     *   "time_added": <time added in seconds>, // Time added in seconds
     *   "txn_bundle": "<base64 string>", // encoded capnp message
     *   "pubkey": "<hex npub>", // hex npub
     *   "user_id": "<UUID>", // User ID
     *   "executed_at": <timestamp_ms> // Execution timestamp in milliseconds
     * },
     * "current_tier_ends": {
     *   "plus": <timestamp_ms> // tier end timestamp in milliseconds
     *  }
     * }
     */
    $decoded = json_decode($response, true);
    // Check for errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log('[NostrLand] submitPayload failed to decode JSON: ' . json_last_error_msg());
      throw new \RuntimeException('Failed to decode JSON response: ' . $response);
    }

    error_log('[NostrLand] submitPayload success' . (is_array($decoded) ? ' data=' . json_encode($decoded) : ''));
    return $decoded;
  }

  /**
   * Calculate time duration for NostrLand activation considering existing subscription
   * If user has existing NostrLand subscription, calculate from when it expires
   * Otherwise, calculate from now
   *
   * @return int Time in seconds to add to NostrLand subscription
   */
  private function calculateActivationTimeSeconds(): int
  {
    $planUntilDate = $this->account->getAccountPlanUntilDate();
    
    // Check if user has existing NostrLand subscription
    if ($this->account->hasNlSubActivation()) {
      $subscriptionInfo = $this->getSubscriptionInfo();
      
      // Calculate our activation end date using executed_at + time_added
      if ($subscriptionInfo && 
          isset($subscriptionInfo['activated_at']) && 
          isset($subscriptionInfo['time_added_seconds'])) {
        
        $lastExecutedAt = $subscriptionInfo['activated_at'];
        $lastTimeAdded = $subscriptionInfo['time_added_seconds'];
        
        // Calculate when OUR activation expires (not the total tier end)
        $ourActivationEndTimestamp = strtotime($lastExecutedAt) + $lastTimeAdded;
        $ourActivationEndDate = date('Y-m-d H:i:s', $ourActivationEndTimestamp);
        
        error_log('[NostrLand] calculateActivationTimeSeconds from our activation end: ' . $ourActivationEndDate . ' to plan end: ' . $planUntilDate);
        
        $planEndTimestamp = strtotime($planUntilDate);
        
        // Calculate seconds from our activation end to plan end
        $timeSeconds = max(0, $planEndTimestamp - $ourActivationEndTimestamp);
        
        // If our activation already extends beyond plan end, no additional time needed
        if ($timeSeconds <= 0) {
          error_log('[NostrLand] calculateActivationTimeSeconds our activation already extends beyond plan');
          return 0;
        }
        
        return $timeSeconds;
      }
    }
    
    // Default: calculate from now to plan end (for new activations)
    error_log('[NostrLand] calculateActivationTimeSeconds from now to plan end: ' . $planUntilDate);
    return $this->convertEndDateToSeconds($planUntilDate);
  }

  /**
   * Activate NostrLand Plus subscription for the user
   * Checks eligibility first, then performs activation and updates account
   *
   * @return array|null Returns activation response or null if not eligible/already activated
   * @throws \RuntimeException If activation fails
   */
  public function activateSubscription(): ?array
  {
    error_log('[NostrLand] activateSubscription start npub=' . substr($this->account->getNpub(), 0, 12) . '…');

    // Check if account is eligible for NostrLand Plus
    if (!$this->account->isAccountNostrLandPlusEligible()) {
      error_log('[NostrLand] activateSubscription not eligible');
      return null;
    }

    // Generate idempotency ID based on current plan_until_date
    $idempotencyId = $this->generateIdempotencyId();
    
    // Check if already activated with this idempotency ID
    if ($this->account->getNlSubActivationId() === $idempotencyId) {
      error_log('[NostrLand] activateSubscription already activated with id=' . $idempotencyId);
      return null;
    }

    // Calculate time duration considering existing subscription
    $timeSeconds = $this->calculateActivationTimeSeconds();

    if ($timeSeconds <= 0) {
      error_log('[NostrLand] activateSubscription no additional time needed');
      return null;
    }

    // Prepare and submit activation payload
    $payload = $this->preparePayload($idempotencyId, $this->account->getNpub(), $timeSeconds, 'plus');
    $response = $this->submitPayload($payload);

    // Update account with activation details
    $this->updateAccountWithActivation($idempotencyId, $response);

    error_log('[NostrLand] activateSubscription success id=' . $idempotencyId);
    return $response;
  }

  /**
   * Handle plan renewal activation
   * Activates NostrLand Plus if user is eligible and was previously activated
   *
   * @return array|null Returns activation response or null if not applicable
   */
  public function handlePlanRenewal(): ?array
  {
    error_log('[NostrLand] handlePlanRenewal start npub=' . substr($this->account->getNpub(), 0, 12) . '…');

    // Check if account is eligible for NostrLand Plus
    if (!$this->account->isAccountNostrLandPlusEligible()) {
      error_log('[NostrLand] handlePlanRenewal not eligible');
      return null;
    }

    // Check if user was previously activated (has activation data)
    if (!$this->account->hasNlSubActivation()) {
      error_log('[NostrLand] handlePlanRenewal no previous activation');
      return null;
    }

    // Perform activation for the new plan period
    return $this->activateSubscription();
  }

  /**
   * Update account with activation details
   *
   * @param string $idempotencyId The idempotency ID used for activation
   * @param array $response The API response from activation
   */
  private function updateAccountWithActivation(string $idempotencyId, array $response): void
  {
    error_log('[NostrLand] updateAccountWithActivation id=' . $idempotencyId);

    // Extract actual activation date from API response (executed_at timestamp in milliseconds)
    $activationDate = date('Y-m-d H:i:s');
    if (isset($response['request']['executed_at'])) {
      $executedAtMs = $response['request']['executed_at'];
      $activationDate = date('Y-m-d H:i:s', intval($executedAtMs / 1000)); // Convert ms to seconds
    }

    // Update account in database with activation details
    $this->account->updateAccount(
      nl_sub_activated_date: $activationDate,
      nl_sub_activation_id: $idempotencyId,
      nl_sub_activation_return_value: $response
    );

    error_log('[NostrLand] updateAccountWithActivation complete with date=' . $activationDate);
  }

  /**
   * Get parsed NostrLand subscription info from stored JSON data
   *
   * @return array|null Parsed subscription info or null if no activation
   */
  public function getSubscriptionInfo(): ?array
  {
    if (!$this->account->hasNlSubActivation()) {
      return null;
    }

    $returnValue = $this->account->getNlSubActivationReturnValue();
    if (!$returnValue) {
      return null;
    }

    $info = [
      'tier' => null,
      'activated_at' => $this->account->getNlSubActivatedDate(),
      'activation_id' => $this->account->getNlSubActivationId(),
      'tier_ends_at' => null,
      'time_added_seconds' => null,
      'api_user_id' => null,
      'idempotency_id' => null
    ];

    // Parse tier from request
    if (isset($returnValue['request']['tier'])) {
      $info['tier'] = $returnValue['request']['tier'];
    }

    // Parse time added from request  
    if (isset($returnValue['request']['time_added'])) {
      $info['time_added_seconds'] = $returnValue['request']['time_added'];
    }

    // Parse API user ID from request
    if (isset($returnValue['request']['user_id'])) {
      $info['api_user_id'] = $returnValue['request']['user_id'];
    }

    // Parse idempotency ID from response
    if (isset($returnValue['id'])) {
      $info['idempotency_id'] = $returnValue['id'];
    }

    // Parse tier end timestamp from current_tier_ends (convert ms to datetime)
    if (isset($returnValue['current_tier_ends']['plus'])) {
      $tierEndMs = $returnValue['current_tier_ends']['plus'];
      $info['tier_ends_at'] = date('Y-m-d H:i:s', intval($tierEndMs / 1000));
    }

    // Parse actual execution timestamp if available
    if (isset($returnValue['request']['executed_at'])) {
      $executedAtMs = $returnValue['request']['executed_at'];
      $info['activated_at'] = date('Y-m-d H:i:s', intval($executedAtMs / 1000));
    }

    return $info;
  }

  /**
   * Process NostrLand activation based on account state
   * This is a convenience method to handle both new activations and renewals
   *
   * @return array|null Returns activation response or null if not applicable
   */
  public function processActivation(): ?array
  {
    error_log('[NostrLand] processActivation start npub=' . substr($this->account->getNpub(), 0, 12) . '…');

    // Always try activation first (handles both new and renewal cases)
    $result = $this->activateSubscription();
    
    if ($result !== null) {
      error_log('[NostrLand] processActivation success');
      return $result;
    }

    error_log('[NostrLand] processActivation no action taken');
    return null;
  }
}
