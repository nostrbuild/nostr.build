<?php

require_once __DIR__ . '/../../SiteConfig.php';

/**
 * Generates CDN URLs for uploaded media.
 *
 * Extracted from MultimediaUpload to provide a standalone, testable
 * component for building media, thumbnail, and responsive-image URLs.
 */
class MediaUrlGenerator
{
    /**
     * Maps internal type names to SiteConfig CDN_CONFIGS keys.
     */
    private const TYPE_MAP = [
        'picture' => 'image',
        'image'   => 'image',
        'video'   => 'video',
        'audio'   => 'audio',
        'profile' => 'profile_picture',
        'unknown' => 'unknown',
    ];

    private bool $pro;
    private ?string $apiClient;

    public function __construct(bool $pro, ?string $apiClient = null)
    {
        $this->pro = $pro;
        $this->apiClient = $apiClient;
    }

    /**
     * Resolve the internal type through the type map and optional apiClient prefix.
     */
    private function resolveType(string $type): string
    {
        $mapped = self::TYPE_MAP[$type] ?? $type;
        if ($this->apiClient !== null) {
            $mapped = $this->apiClient . '_' . $mapped;
        }
        return ($this->pro ? 'professional_account_' : '') . $mapped;
    }

    /**
     * Get the S3 object-key prefix for a given media type.
     */
    public function prefix(string $type = 'unknown'): string
    {
        try {
            return SiteConfig::getS3Path($this->resolveType($type));
        } catch (\Exception $e) {
            error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return SiteConfig::getS3Path('unknown');
        }
    }

    /**
     * Generate the primary CDN URL for a media file.
     */
    public function mediaURL(string $fileName, string $type): string
    {
        try {
            return SiteConfig::getFullyQualifiedUrl($this->resolveType($type)) . $fileName;
        } catch (\Exception $e) {
            error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return SiteConfig::getFullyQualifiedUrl('unknown') . $fileName;
        }
    }

    /**
     * Generate the thumbnail CDN URL for an image file.
     */
    public function thumbnailURL(string $fileName, string $type): string
    {
        try {
            return SiteConfig::getThumbnailUrl($this->resolveType($type)) . $fileName;
        } catch (\Exception $e) {
            error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return SiteConfig::getThumbnailUrl('unknown') . $fileName;
        }
    }

    /**
     * Generate responsive-image CDN URLs at standard resolutions.
     *
     * @return array<string, string> Keyed by resolution label (e.g. '720p').
     */
    public function responsiveURLs(string $fileName, string $type): array
    {
        $resolvedType = $this->resolveType($type);
        $resolutions = ['240p', '360p', '480p', '720p', '1080p'];
        $urls = [];

        foreach ($resolutions as $resolution) {
            try {
                $urls[$resolution] = SiteConfig::getResponsiveUrl($resolvedType, $resolution) . $fileName;
            } catch (\Exception $e) {
                error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
                $urls[$resolution] = SiteConfig::getResponsiveUrl('unknown', $resolution) . $fileName;
            }
        }

        return $urls;
    }
}
