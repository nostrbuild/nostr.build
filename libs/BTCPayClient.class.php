<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

class BTCPayClient
{

  private $apiKey;
  private $host;
  private $storeId;
  private $invoice;
  private $currency;

  public function __construct(string $apiKey, string $host, string $storeId, string $currency = null)
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

  public function createInvoice(string $amount, string $redirectUrl = '', array $metadata = []): string
  {
    $invoiceAmount = PreciseNumber::parseString($amount);
    $checkoutOptions = new InvoiceCheckoutOptions();
    $checkoutOptions
      //->setSpeedPolicy($checkoutOptions::SPEED_HIGH)
      //->setExpirationMinutes(45)
      //->setMonitoringMinutes(120)
      ->setPaymentMethods([$this->currency, 'BTC'])
      //->setPaymentTolerance(0)
      ->setRedirectAutomatically(empty($redirectUrl) ? false : true)
      ->setRedirectURL($redirectUrl);
    try {
      $invoice = $this->invoice->createInvoice(
        $this->storeId,
        $this->currency,
        $invoiceAmount,
        null,
        null,
        $metadata,
        $checkoutOptions
      );
    } catch (Exception $e) {
      error_log($e->getMessage());
      return '';
    }
    return $invoice->getId();
  }
}
