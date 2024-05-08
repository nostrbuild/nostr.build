<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\PullPayment;
use BTCPayServer\Util\PreciseNumber;

class PullPayments
{
    public $apiKey;
    public $host;
    public $storeId;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
        $this->storeId = '';
    }

    public function getStorePullPayments()
    {
        $includeArchived = true;

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->getStorePullPayments(
                $this->storeId,
                $includeArchived
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createPullPayment()
    {
        $paymentName = 'TestPayout-' . rand(0, 10000000);
        $paymentAmount = PreciseNumber::parseString('0.000001');
        $paymentCurrency = 'BTC';
        $paymentPeriod = null;
        $boltExpiration = 1;
        $autoApproveClaims = false;
        $startsAt = null;
        $expiresAt = null;
        $paymentMethods = ['BTC'];

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump(
                $client->createPullPayment(
                    $this->storeId,
                    $paymentName,
                    $paymentAmount,
                    $paymentCurrency,
                    $paymentPeriod,
                    $boltExpiration,
                    $autoApproveClaims,
                    $startsAt,
                    $expiresAt,
                    $paymentMethods
                )
            );
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function archivePullPayment()
    {
        $pullPaymentId = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->archivePullPayment(
                $this->storeId,
                $pullPaymentId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function cancelPayout()
    {
        $payoutId = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->cancelPayout(
                $this->storeId,
                $payoutId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function markPayoutAsPaid()
    {
        $payoutId = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->markPayoutAsPaid(
                $this->storeId,
                $payoutId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function approvePayout()
    {
        $payoutId = '';
        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->approvePayout(
                $this->storeId,
                $payoutId,
                0,
                null
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getPullPayment()
    {
        $pullPaymentId = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->getPullPayment(
                $this->storeId,
                $pullPaymentId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getPayouts()
    {
        $pullPaymentId = '';
        $includeCancelled = true;

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->getPayouts(
                $pullPaymentId,
                $includeCancelled
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createPayout()
    {
        $pullPaymentId = '';
        $destination = '';
        $amount = PreciseNumber::parseString('0.000001');
        $paymentMethod = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->createPayout(
                $pullPaymentId,
                $destination,
                $amount,
                $paymentMethod
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getPayout()
    {
        $pullPaymentId = '';
        $payoutId = '';

        try {
            $client = new PullPayment($this->host, $this->apiKey);
            var_dump($client->getPayout(
                $pullPaymentId,
                $payoutId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$pp = new PullPayments();
//$pp->createPullPayment();
//$pp->getStorePullPayments();
//$pp->archivePullPayment();
//$pp->cancelPayout();
//$pp->markPayoutAsPaid();
//$pp->approvePayout();
//$pp->getPullPayment();
//$pp->getPayouts();
//$pp->createPayout();
//$pp->getPayout();
