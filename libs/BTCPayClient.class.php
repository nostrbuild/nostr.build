<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Result\Invoice as ResultInvoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

class BTCPayClient
{

  private $apiKey;
  private $host;
  private $storeId;
  private $invoice;
  private $currency;

  public function __construct(string $apiKey, string $host, string $storeId, string $currency = 'SATS')
  {
    $this->apiKey = $apiKey;
    $this->host = $host;
    $this->storeId = $storeId;
    $this->currency = $currency;

    // Create a new invoice client instance
    $this->invoice = new Invoice($this->host, $this->apiKey);
  }

  public function getInvoice($invoiceId): BTCPayServer\Result\Invoice
  {
    return $this->invoice->getInvoice($this->storeId, $invoiceId);
  }

  public function createInvoice(string $amount, string $redirectUrl = '', array $metadata = [], string $orderIdPrefix = 'nb_signup_order', bool $returnInvoice = false): string | ResultInvoice
  {
    $invoiceAmount = PreciseNumber::parseString($amount);
    $checkoutOptions = new InvoiceCheckoutOptions();
    $checkoutOptions
      ->setSpeedPolicy($checkoutOptions::SPEED_HIGH)
      ->setExpirationMinutes(240)
      ->setMonitoringMinutes(480)
      ->setPaymentMethods(['BTC-LNURLPay', 'BTC-LightningNetwork', 'BTC'])
      ->setPaymentTolerance(0)
      ->setRedirectAutomatically(empty($redirectUrl) ? false : true)
      ->setRedirectURL($redirectUrl);
    try {
      $order_id = uniqid($orderIdPrefix . '-', true);
      $invoice = $this->invoice->createInvoice(
        $this->storeId,
        $this->currency,
        $invoiceAmount,
        $order_id,
        null,
        $metadata,
        $checkoutOptions
      );
    } catch (Exception $e) {
      error_log($e->getMessage());
      return '';
    }
    return ($returnInvoice ? $invoice : $invoice->getId());
  }

  static public function amountEqual(int $intAmount, PreciseNumber $pnAmount): bool
  {
    // Parse integer to PreciseNumber
    $preciseFirstNumber = PreciseNumber::parseInt($intAmount);
    // Conver both to string and compare
    return (string)$preciseFirstNumber === (string)$pnAmount;
  }

  static public function amountEqualString(string $strAmount, PreciseNumber $pnAmount): bool
  {
    // Parse string to PreciseNumber
    $preciseFirstNumber = PreciseNumber::parseString($strAmount);
    // Conver both to string and compare
    return (string)$preciseFirstNumber === (string)$pnAmount;
  }

  static public function amountEquealOrMore(int $intAmount, PreciseNumber $pnAmount): bool
  {
    // Parse integer to PreciseNumber
    $preciseFirstNumber = PreciseNumber::parseInt($intAmount);
    // Conver both to string to integer and compare
    return intval($preciseFirstNumber) >= intval($pnAmount);
  }

  static public function amountEquealOrMoreString(string $strAmount, PreciseNumber $pnAmount): bool
  {
    // Parse string to PreciseNumber
    $preciseFirstNumber = PreciseNumber::parseString($strAmount);
    // Conver both to string to integer and compare
    return intval($preciseFirstNumber) >= intval($pnAmount);
  }

  static public function intToString(int $intAmount): string
  {
    return (string)PreciseNumber::parseInt($intAmount);
  }
}
