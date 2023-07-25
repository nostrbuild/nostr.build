<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\Webhook;

class BTCPayClient
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
  }
  public function getInvoice($invoiceId): BTCPayServer\Result\Invoice
  {
    return $this->invoice->getInvoice($this->storeId, $invoiceId);
  }
}
