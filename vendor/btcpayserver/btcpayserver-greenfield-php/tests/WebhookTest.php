<?php

declare(strict_types=1);

namespace BTCPayServer\Tests;

use BTCPayServer\Client\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    /**
     * In version 1.3, we made the method Webhook::isIncomingWebhookRequestValid() static.
     * This test proves that making this method static is not a breaking change.
     * If your legacy code calls the method as a class method, you still get the same result even though it has been made static now.
     */
    public function testValidateWebhookStaticCall(): void
    {
        $requestBody = '{"a":1,"b":2,"c":3}';
        $btcpaySigHeader = 'sha256=bc8122472fb9bb9c15d90d7a61b156f54a69d835592c85d0f37f6454d530dec8';
        $secret = 'abc';

        $baseUrl = 'https://www.btcpayserver.org';
        $apiKey = 'XXX';

        $webhookClient = new Webhook($baseUrl, $apiKey);

        try {
            $a = $webhookClient->isIncomingWebhookRequestValid($requestBody, $btcpaySigHeader, $secret);
        } catch (\Throwable $t) {
            $a = 'ERROR: ' . $t->getMessage();
        }
        $this->assertTrue($a, 'When "\BTCPayServer\Client\Webhook::isIncomingWebhookRequestValid()" is called as a class member method it should return true.');

        try {
            $b = Webhook::isIncomingWebhookRequestValid($requestBody, $btcpaySigHeader, $secret);
        } catch (\Throwable $t) {
            $b = 'ERROR: ' . $t->getMessage();
        }
        $this->assertTrue($b, 'When "\BTCPayServer\Client\Webhook::isIncomingWebhookRequestValid()" is called statically it should return true.');
    }
}
