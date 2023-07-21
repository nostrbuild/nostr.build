<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Health;

class HealthClass
{
    public $apiKey;
    public $host;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
    }

    public function getHealthStatus()
    {
        try {
            $client = new Health($this->host, $this->apiKey);
            var_dump($client->getHealthStatus());
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$health = new HealthClass();
$health->getHealthStatus();
