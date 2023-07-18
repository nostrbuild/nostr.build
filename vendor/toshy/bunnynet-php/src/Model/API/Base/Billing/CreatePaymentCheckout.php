<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Billing;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class CreatePaymentCheckout implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'billing/payment/checkout';
    }

    public function getHeaders(): array
    {
        return [
            Header::ACCEPT_JSON,
        ];
    }

    public function getBody(): array
    {
        return [
            new AbstractParameter(name: 'RechargeAmount', type: Type::NUMERIC_TYPE, required: true),
            new AbstractParameter(name: 'PaymentMethodToken', type: Type::NUMERIC_TYPE),
            new AbstractParameter(name: 'PaymentRequestId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Nonce', type: Type::STRING_TYPE, required: true),
        ];
    }
}
