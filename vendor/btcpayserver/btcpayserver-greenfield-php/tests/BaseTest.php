<?php

declare(strict_types=1);

namespace BTCPayServer\Tests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    protected string $host;
    protected string $apiKey;
    protected string $nodeUri;
    protected string $storeId;

    public static function setUpBeforeClass(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        if (!isset($_ENV['BTCPAY_API_KEY'], $_ENV['BTCPAY_HOST'], $_ENV['BTCPAY_STORE_ID'], $_ENV['BTCPAY_NODE_URI'])) {
            throw new \Exception('Missing .env variables');
        }
    }

    protected function setUp(): void
    {
        $this->host = $_ENV['BTCPAY_HOST'];
        $this->apiKey = $_ENV['BTCPAY_API_KEY'];
        $this->nodeUri = $_ENV['BTCPAY_NODE_URI'];
        $this->storeId = $_ENV['BTCPAY_STORE_ID'];
    }

    public function testThatAllTheVariablesAreSet(): void
    {
        $this->assertIsString($this->apiKey);
        $this->assertIsString($this->host);
        $this->assertIsString($this->storeId);
        $this->assertIsString($this->nodeUri);

        $this->assertNotEmpty($this->apiKey);
        $this->assertNotEmpty($this->host);
        $this->assertNotEmpty($this->storeId);
        $this->assertNotEmpty($this->nodeUri);
    }
}
