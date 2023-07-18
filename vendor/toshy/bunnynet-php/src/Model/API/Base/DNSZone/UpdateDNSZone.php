<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\DNSZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class UpdateDNSZone implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'dnszone/%d';
    }

    public function getHeaders(): array
    {
        return [
            Header::ACCEPT_JSON,
            Header::CONTENT_TYPE_JSON,
        ];
    }

    public function getBody(): array
    {
        return [
            new AbstractParameter(name: 'CustomNameserversEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'Nameserver1', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Nameserver2', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'SoaEmail', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'LoggingEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LogAnonymizationType', type: Type::INT_TYPE),
            new AbstractParameter(name: 'LoggingIPAnonymizationEnabled', type: Type::BOOLEAN_TYPE),
        ];
    }
}
