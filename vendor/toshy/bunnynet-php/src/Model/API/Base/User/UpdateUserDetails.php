<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\User;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class UpdateUserDetails implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'user';
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
            new AbstractParameter(name: 'FirstName', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Email', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'BillingEmail', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'LastName', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'StreetAddress', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'City', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ZipCode', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Country', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'CompanyName', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'VATNumber', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ReceiveNotificationEmails', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ReceivePromotionalEmails', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'Password', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'OldPassword', type: Type::STRING_TYPE),
        ];
    }
}
