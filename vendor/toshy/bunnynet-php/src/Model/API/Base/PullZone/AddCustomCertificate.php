<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\PullZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class AddCustomCertificate implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'pullzone/%d/addCertificate';
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
            new AbstractParameter(name: 'Hostname', type: Type::STRING_TYPE, required: true),
            new AbstractParameter(name: 'Certificate', type: Type::STRING_TYPE, required: true),
            new AbstractParameter(name: 'CertificateKey', type: Type::STRING_TYPE, required: true),
        ];
    }
}
