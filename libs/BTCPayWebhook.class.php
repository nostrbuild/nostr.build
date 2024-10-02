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
    $payload = json_decode($requestBody, false, 512, JSON_THROW_ON_ERROR);
    if (!isset($payload->invoiceId) && true === empty($payload->invoiceId)) {
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
    } else {
      try {
        global $link;
        // Get the user npub from the invoice metadata
        $userNpub = $invoice->getData()['metadata']['userNpub'];
        // Get the item code from the invoice metadata
        $accountPlan = $invoice->getData()['metadata']['plan'];
        // Get the order ID from the invoice metadata
        $orderId = $invoice->getData()['metadata']['orderId'];
        // Get the order type from the invoice metadata (new, renewal, upgrade)
        $orderType = $invoice->getData()['metadata']['orderType'];
        // Get the order period from the invoice metadata
        $orderPeriod = $invoice->getData()['metadata']['orderPeriod'];
        // Get purchasePrice from the invoice
        $purchasePrice = $invoice->getData()['metadata']['purchasePrice'];

        // Credits fill-up orders
        $purchasedCredits = intval($invoice->getData()['metadata']['purchasedCredits']);
        $bonusCredits = 0;
        if ($purchasedCredits !== 0 && $orderType === 'credits-topup') {
          $predictedPurchasePrice = $purchasedCredits * 50; // 50 is the price per credit
          // Ensure that the purchase price is equal to the predicted purchase price
          if (intval($purchasePrice) !== $predictedPurchasePrice) {
            error_log("The actual purchase price is not equal to the predicted purchase price." . PHP_EOL);
            return false;
          }
          // Apply bonus credits based on the purchase size
          if ($purchasedCredits >= 500 && $purchasedCredits < 1000) {
            // Apply 5% bonus credits
            $bonusCredits = intval($purchasedCredits * 0.05);
          } elseif ($purchasedCredits >= 1000) {
            $bonusCredits = intval($purchasedCredits * 0.1);
          }
        }

        // Compare the actual amount paid with the purchase price
        if (!BTCPayClient::amountEqual($purchasePrice, $invoice->getAmount())) {
          error_log("The actual amount paid is less than the purchase price." . PHP_EOL);
          return false;
        }

        error_log(PHP_EOL . "Order ID: " . $orderId . PHP_EOL);
        error_log("User npub: " . $userNpub . PHP_EOL);
        error_log("Account plan: " . $accountPlan . PHP_EOL);
        error_log("Order type: " . $orderType . PHP_EOL);
        error_log("Order period: " . $orderPeriod . PHP_EOL);
        error_log("Purchase price: " . $purchasePrice . PHP_EOL);
        error_log("Purchased credits: " . $purchasedCredits . PHP_EOL);

        if ($purchasedCredits !== 0 && $orderType === 'credits-topup') {
          $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
          $credits = new Credits($userNpub, $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
          $credits->topupCredits($purchasedCredits, $orderId, $invoice->getData());
        } else {
          // Create a new account instance
          $account = new Account($userNpub, $link);
          // Update the account plan and end date
          $new = $orderType !== 'renewal'; // If the order type is not renewal, then it is a new order
          $account->setPlan((int)$accountPlan, (string)$orderPeriod, $new);
          error_log("[INFO] Account " . $account->getNpub() . ' updated to a new plan: ' . $accountPlan . PHP_EOL);
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
