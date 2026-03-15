<?php

require_once __DIR__ . '/../CloudflareUploadWebhook.class.php';

class WebhookNotifier
{
    private CloudflareUploadWebhook $webhook;

    public function __construct(CloudflareUploadWebhook $webhook)
    {
        $this->webhook = $webhook;
    }

    /**
     * Send a webhook notification (fire-and-forget).
     * Catches and logs exceptions internally so callers are never interrupted.
     *
     * @param array $params Keys match CloudflareUploadWebhook::createPayload() named parameters.
     */
    public function notify(array $params): void
    {
        try {
            $this->webhook->createPayload(
                fileHash:          $params['fileHash'],
                fileName:          $params['fileName'],
                fileSize:          $params['fileSize'],
                fileMimeType:      $params['fileMimeType'],
                fileUrl:           $params['fileUrl'],
                fileType:          $params['fileType'],
                uploadAccountType: $params['uploadAccountType'],
                shouldTranscode:   $params['shouldTranscode'] ?? false,
                uploadTime:        $params['uploadTime'] ?? null,
                fileOriginalUrl:   $params['fileOriginalUrl'] ?? null,
                uploadNpub:        $params['uploadNpub'] ?? null,
                uploadedFileInfo:  $params['uploadedFileInfo'] ?? null,
                orginalSha256Hash: $params['originalSha256Hash'] ?? null,
                currentSha256Hash: $params['currentSha256Hash'] ?? null,
                doVirusScan:       $params['doVirusScan'] ?? false,
            );
            $this->webhook->sendPayload();
        } catch (\Exception $e) {
            error_log('WebhookNotifier::notify failed: ' . $e->getMessage());
        }
    }

    /**
     * Build a params array suitable for passing to notify().
     */
    public static function buildParams(
        string  $fileHash,
        string  $fileName,
        int     $fileSize,
        string  $fileMimeType,
        string  $fileUrl,
        string  $fileType,
        bool    $shouldTranscode,
        string  $uploadAccountType,
        ?string $uploadedFileInfo,
        ?string $uploadNpub,
        ?string $fileOriginalUrl,
        string  $originalSha256Hash,
        string  $currentSha256Hash,
        bool    $doVirusScan,
    ): array {
        return [
            'fileHash'           => $fileHash,
            'fileName'           => $fileName,
            'fileSize'           => $fileSize,
            'fileMimeType'       => $fileMimeType,
            'fileUrl'            => $fileUrl,
            'fileType'           => $fileType,
            'shouldTranscode'    => $shouldTranscode,
            'uploadAccountType'  => $uploadAccountType,
            'uploadTime'         => time(),
            'uploadedFileInfo'   => $uploadedFileInfo,
            'uploadNpub'         => $uploadNpub,
            'fileOriginalUrl'    => $fileOriginalUrl,
            'originalSha256Hash' => $originalSha256Hash,
            'currentSha256Hash'  => $currentSha256Hash,
            'doVirusScan'        => $doVirusScan,
        ];
    }
}
