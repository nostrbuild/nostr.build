<?php

declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Credits.class.php';
require_once __DIR__ . '/Account.class.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\Webhook;

class BTCPayWebhook
{

  private $apiKey;
  private $host;
  private $storeId;
  private $secret;
  private $invoice;
  private $webhook;

  public function __construct(string $apiKey, string $host, string $storeId, string $secret)
  {
    $this->apiKey = $apiKey;
    $this->host = $host;
    $this->storeId = $storeId;
    $this->secret = $secret;

    // Create a new invoice client instance
    $this->invoice = new Invoice($this->host, $this->apiKey);
    // Create a new webhook client instance
    $this->webhook = new Webhook($this->host, $this->apiKey);
  }

  // Process the webhook
  public function processWebhook($requestBody, $requestSignature): bool
  {

    // Convert requestBody into an object and check if it has an invoiceId
    try {
      $payload = json_decode($requestBody, false, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
      error_log("Invalid webhook payload JSON: " . $e->getMessage() . PHP_EOL);
      return false;
    }

    if (!isset($payload->invoiceId) || empty($payload->invoiceId)) {
      error_log("The request body is invalid or missing invoice ID." . PHP_EOL);
      return false;
    }

    // Verify the request signature
    if (!$this->webhook->isIncomingWebhookRequestValid($requestBody, $requestSignature, $this->secret)) {
      error_log("The request signature is invalid." . PHP_EOL);
      return false;
    }

    // Check whether your webhook is of the desired type
    // We want to accept all events and not just InvoiceSettled
    /*
    if ($payload->type !== "InvoiceSettled") {
      throw new \RuntimeException(
        'Invalid payload message type. Only InvoiceSettled is supported, check the configuration of the webhook.'
      );
    }
    */

    // Get the invoice
    $invoice = null;
    try {
      $invoice = $this->getInvoice($payload->invoiceId);
    } catch (Exception $e) {
      error_log("Failed to fetch invoice data: " . $e . PHP_EOL);
      return false;
    }

    /*
    $invoice->getData()['metadata'] = Array
    (
    [orderId] => <order ID>
    [itemCode] => <itemCode that should map to the account level>
    [itemDesc] => <Decsription of the item>
    [orderUrl] => https://<URL of the shop POS>
    [physical] => <N/A>
    [userNpub] => <user suppled npub>
    [receiptData] => Array
        (
            [Title] => <Item Title>
            [Description] => <Item Description>
        )
    )
    */
    error_log("Invoice metadata: " . print_r($invoice->getData()['metadata'], true) . PHP_EOL);
    error_log("Invoice data: " . print_r($invoice->getData(), true) . PHP_EOL);

    // Check if the invoice is paid
    if (!$invoice->isSettled()) {
      error_log("Invoice is not paid." . PHP_EOL);
      error_log("Invoice status: " . print_r($invoice, true) . PHP_EOL);
      return true;
    } else {
      try {
        global $link;
        $invoiceData = $invoice->getData();
        $metadata = $invoiceData['metadata'] ?? [];

        // Get the user npub from the invoice metadata
        $userNpub = $metadata['userNpub'] ?? '';
        // Get the item code from the invoice metadata
        $accountPlan = isset($metadata['plan']) ? (int)$metadata['plan'] : 0;
        // Get the order ID from the invoice metadata
        $orderId = $metadata['orderId'] ?? ($invoiceData['orderId'] ?? $invoice->getId());
        // Get the order type from the invoice metadata (new, renewal, upgrade)
        $orderType = $metadata['orderType'] ?? '';
        // Get the order period from the invoice metadata
        $orderPeriod = $metadata['orderPeriod'] ?? '';
        // Get purchasePrice from the invoice
        $purchasePrice = $metadata['purchasePrice'] ?? null;
        // Get referral code from the invoice
        $referralCode = $metadata['referralCode'] ?? '';

        if (empty($userNpub) || empty($orderType)) {
          error_log("Missing required invoice metadata fields." . PHP_EOL);
          return false;
        }

        $amountMatches = is_string($purchasePrice) || is_numeric($purchasePrice)
          ? BTCPayClient::amountEqualString((string)$purchasePrice, $invoice->getAmount())
          : false;

        // Compare the actual amount paid with the purchase price
        if (!$amountMatches) {
          error_log("The actual amount paid is less than the purchase price." . PHP_EOL);
          return false;
        }

        error_log(PHP_EOL . "Order ID: " . $orderId . PHP_EOL);
        error_log("User npub: " . $userNpub . PHP_EOL);
        error_log("Account plan: " . $accountPlan . PHP_EOL);
        error_log("Order type: " . $orderType . PHP_EOL);
        error_log("Order period: " . $orderPeriod . PHP_EOL);
        error_log("Purchase price: " . $purchasePrice . PHP_EOL);

        $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
        $credits = new Credits($userNpub, $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
        if ($orderType === 'credits-topup') {
          // Apply credits based on the invoice
          $credits->applyCreditsBasedOnInvoiceId(invoice: $invoice);
        } else {
          // Create a new account instance
          $account = new Account($userNpub, $link);
          // Update the account plan and end date
          $new = $orderType !== 'renewal'; // If the order type is not renewal, then it is a new order
          $account->setPlan((int)$accountPlan, (string)$orderPeriod, $new);
          error_log("[INFO] Account " . $account->getNpub() . ' updated to a new plan: ' . $accountPlan . PHP_EOL);
          // Process referral code and only for orderType 'signup'
          if ($orderType !== 'signup') return true;
          // Check if the code matches the format [A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4} and 14 characters long
          if (!preg_match('/^[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/', $referralCode)) {
            error_log("[ERROR] Invalid referral code: " . $referralCode . PHP_EOL);
            // Ignore, no bonus credits for you then.
            return true;
          }
          // Get npub of the referrer
          // Function will check if account is valid (level > 0) and not expired (remaining days > 0)
          $referrerNpub = findNpubByReferralCode($link, $referralCode);
          if (empty($referrerNpub)) {
            error_log("[ERROR] Referral code not found: " . $referralCode . PHP_EOL);
            return true;
          }
          $referrerAccount = new Account($referrerNpub, $link);
          // Make sure that account has a valid plan level, 1, 2, 10
          $referrerValidLevels = [1, 2, 10];
          if (!in_array($referrerAccount->getAccountLevelInt(), $referrerValidLevels)) {
            error_log("[ERROR] Referrer account has no valid plan level: " . $referrerNpub . PHP_EOL);
            return true;
          }
          // Add bonus credits to the referrer
          $referrerCredits = new Credits($referrerNpub, $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
          $referralCredits = new Credits($userNpub, $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
          // Fetch balances to init the accounts if not done yet before this transaction
          $referrerCredits->getCreditsBalance();
          $referralCredits->getCreditsBalance();
          // Get initial signup bonus credits based on the level and subscription period
          $referralInitialBonus = Credits::getInitBonusCredits($accountPlan, $orderPeriod);
          // Check if it's not 0
          if ($referralInitialBonus === 0) {
            error_log("[ERROR] Initial bonus credits is 0 for plan: " . $accountPlan . " and period: " . $orderPeriod . PHP_EOL);
            return true;
          }
          // Based on a 10% of the initial bonus credits, split between the referrer and the referred
          $referralBonus = intval($referralInitialBonus * 0.1 / 2);
          // Apply bonus credits to the referrer and the referred
          $bonusEventId = $invoice->getId();
          $referrerCredits->topupCredits($referralBonus, $bonusEventId . '/referrer-bonus', $invoiceData);
          $referralCredits->topupCredits($referralBonus, $bonusEventId . '/referral-bonus', $invoiceData);
        }
      } catch (Exception $e) {
        error_log("Failed to update account: " . $e . PHP_EOL);
        return false;
      }
    }

    return true;
  }

  public function getInvoice($invoiceId): BTCPayServer\Result\Invoice
  {
    return $this->invoice->getInvoice($this->storeId, $invoiceId);
  }
}
