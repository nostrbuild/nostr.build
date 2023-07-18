<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class UpdateVideoLibrary implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'videolibrary/%d';
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
            new AbstractParameter(name: 'Name', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'CustomHTML', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'PlayerKeyColor', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'EnableTokenAuthentication', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableTokenIPVerification', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ResetToken', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'WatermarkPositionLeft', type: Type::INT_TYPE),
            new AbstractParameter(name: 'WatermarkPositionTop', type: Type::INT_TYPE),
            new AbstractParameter(name: 'WatermarkWidth', type: Type::INT_TYPE),
            new AbstractParameter(name: 'WatermarkHeight', type: Type::INT_TYPE),
            new AbstractParameter(name: 'EnabledResolutions', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ViAiPublisherId', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'VastTagUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'WebhookUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'CaptionsFontSize', type: Type::INT_TYPE),
            new AbstractParameter(name: 'CaptionsFontColor', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'CaptionsBackground', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'UILanguage', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'AllowEarlyPlay', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'PlayerTokenAuthenticationEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'BlockNoneReferrer', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableMP4Fallback', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'KeepOriginalFiles', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'AllowDirectPlay', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableDRM', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'Controls', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Bitrate240p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate360p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate480p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate720p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate1080p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate1440p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'Bitrate2160p', type: Type::INT_TYPE),
            new AbstractParameter(name: 'ShowHeatmap', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableContentTagging', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'FontFamily', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'EnableTranscribing', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableTranscribingTitleGeneration', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableTranscribingDescriptionGeneration', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'TranscribingCaptionLanguages', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
        ];
    }
}
