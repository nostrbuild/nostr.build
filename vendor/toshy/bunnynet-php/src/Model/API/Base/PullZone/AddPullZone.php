<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\PullZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class AddPullZone implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'pullzone';
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
            new AbstractParameter(name: 'OriginUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'AllowedReferrers', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'BlockedReferrers', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'BlockedIps', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'EnableGeoZoneUS', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableGeoZoneEU', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableGeoZoneASIA', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableGeoZoneSA', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableGeoZoneAF', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'BlockRootPathAccess', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'BlockPostRequests', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableQueryStringOrdering', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableWebpVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableAvifVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableMobileVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableCountryCodeVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableHostnameVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableCacheSlice', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ZoneSecurityEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ZoneSecurityIncludeHashRemoteIP', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'IgnoreQueryStrings', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'MonthlyBandwidthLimit', type: Type::INT_TYPE),
            new AbstractParameter(name: 'AccessControlOriginHeaderExtensions', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'EnableAccessControlOriginHeader', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'DisableCookies', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'BudgetRedirectedCountries', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'BlockedCountries', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'CacheControlMaxAgeOverride', type: Type::INT_TYPE),
            new AbstractParameter(name: 'CacheControlPublicMaxAgeOverride', type: Type::INT_TYPE),
            new AbstractParameter(name: 'CacheControlBrowserMaxAgeOverride', type: Type::INT_TYPE),
            new AbstractParameter(name: 'AddHostHeader', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'AddCanonicalHeader', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableLogging', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LoggingIPAnonymizationEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'PermaCacheStorageZoneId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'AWSSigningEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'AWSSigningKey', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'AWSSigningRegionName', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'AWSSigningSecret', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'EnableOriginShield', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginShieldZoneCode', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'EnableTLS1', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableTLS1_1', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'CacheErrorResponses', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'VerifyOriginSSL', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LogForwardingEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LogForwardingHostname', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'LogForwardingPort', type: Type::INT_TYPE),
            new AbstractParameter(name: 'LogForwardingToken', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'LogForwardingProtocol', type: Type::INT_TYPE),
            new AbstractParameter(name: 'LoggingSaveToStorage', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LoggingStorageZoneId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'FollowRedirects', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ConnectionLimitPerIPCount', type: Type::INT_TYPE),
            new AbstractParameter(name: 'RequestLimit', type: Type::INT_TYPE),
            new AbstractParameter(name: 'LimitRateAfter', type: Type::NUMERIC_TYPE),
            new AbstractParameter(name: 'LimitRatePerSecond', type: Type::INT_TYPE),
            new AbstractParameter(name: 'BurstSize', type: Type::INT_TYPE),
            new AbstractParameter(name: 'WAFEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'WAFDisabledRuleGroups', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'WAFDisabledRules', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'WAFEnableRequestHeaderLogging', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'WAFRequestHeaderIgnores', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'ErrorPageEnableCustomCode', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ErrorPageCustomCode', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ErrorPageEnableStatuspageWidget', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'ErrorPageStatuspageCode', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ErrorPageWhitelabel', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerDesktopMaxWidth', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerMobileMaxWidth', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerImageQuality', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerMobileImageQuality', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerEnableWebP', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerEnableManipulationEngine', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerMinifyCSS', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerMinifyJavaScript', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerWatermarkEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerWatermarkUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'OptimizerWatermarkPosition', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerWatermarkOffset', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerWatermarkMinImageSize', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OptimizerAutomaticOptimizationEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OptimizerClasses', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: 'Name', type: Type::INT_TYPE),
                new AbstractParameter(name: 'Properties', type: Type::ARRAY_TYPE, children: [
                    new AbstractParameter(name: null, type: Type::ARRAY_TYPE),
                ]),
            ]),
            new AbstractParameter(name: 'OptimizerForceClasses', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'Type', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginRetries', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OriginConnectTimeout', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OriginResponseTimeout', type: Type::INT_TYPE),
            new AbstractParameter(name: 'UseStaleWhileUpdating', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'UseStaleWhileOffline', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginRetry5XXResponses', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginRetryConnectionTimeout', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginRetryResponseTimeout', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginRetryDelay', type: Type::INT_TYPE),
            new AbstractParameter(name: 'DnsOriginPort', type: Type::INT_TYPE),
            new AbstractParameter(name: 'DnsOriginScheme', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'QueryStringVaryParameters', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'OriginShieldEnableConcurrencyLimit', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginShieldMaxConcurrentRequests', type: Type::INT_TYPE),
            new AbstractParameter(name: 'EnableCookieVary', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'CookieVaryParameters', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'EnableSafeHop', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginShieldQueueMaxWaitTime', type: Type::INT_TYPE),
            new AbstractParameter(name: 'UseBackgroundUpdate', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OriginShieldMaxQueuedRequests', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'UseBackgroundUpdate', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableAutoSSL', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'LogAnonymizationType', type: Type::INT_TYPE),
            new AbstractParameter(name: 'StorageZoneId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'EdgeScriptId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'OriginType', type: Type::INT_TYPE),
            new AbstractParameter(name: 'MagicContainersAppId', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'LogFormat', type: Type::INT_TYPE),
            new AbstractParameter(name: 'LogForwardingFormat', type: Type::INT_TYPE),
            new AbstractParameter(name: 'ShieldDDosProtectionType', type: Type::INT_TYPE),
            new AbstractParameter(name: 'ShieldDDosProtectionEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'OriginHostHeader', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'EnableSmartCache', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'EnableRequestCoalescing', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'RequestCoalescingTimeout', type: Type::INT_TYPE),
            new AbstractParameter(name: 'DisableLetsEncrypt', type: Type::INT_TYPE),
            new AbstractParameter(name: 'EnableBunnyImageAi', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'BunnyAiImageBlueprints', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: 'Name', type: Type::INT_TYPE),
                new AbstractParameter(name: 'Properties', type: Type::ARRAY_TYPE, children: [
                    new AbstractParameter(name: null, type: Type::ARRAY_TYPE),
                ]),
            ]),
            new AbstractParameter(name: 'PreloadingScreenEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'PreloadingScreenCode', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'PreloadingScreenLogoUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'PreloadingScreenTheme', type: Type::INT_TYPE),
            new AbstractParameter(name: 'PreloadingScreenCodeEnabled', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'PreloadingScreenDelay', type: Type::INT_TYPE),
            new AbstractParameter(name: 'RoutingFilters', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::ARRAY_TYPE),
            ]),
            new AbstractParameter(name: 'Name', type: Type::STRING_TYPE, required: true),
        ];
    }
}
