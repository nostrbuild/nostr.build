<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\PullZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class AddOrUpdateEdgeRule implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'pullzone/%d/edgerules/addOrUpdate';
    }

    public function getHeaders(): array
    {
        return [
            Header::CONTENT_TYPE_JSON,
        ];
    }

    public function getBody(): array
    {
        return [
            new AbstractParameter(name: 'Guid', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ActionType', type: Type::INT_TYPE, required: true),
            new AbstractParameter(name: 'ActionParameter1', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ActionParameter2', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Triggers', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: 'Type', type: Type::INT_TYPE),
                new AbstractParameter(name: 'PatternMatches', type: Type::ARRAY_TYPE, children: [
                    new AbstractParameter(name: null, type: Type::STRING_TYPE),
                ]),
                new AbstractParameter(name: 'PatternMatchingType', type: Type::INT_TYPE),
                new AbstractParameter(name: 'Parameter1', type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'TriggerMatchingType', type: Type::INT_TYPE, required: true),
            new AbstractParameter(name: 'Description', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Enabled', type: Type::BOOLEAN_TYPE, required: true),
        ];
    }
}
