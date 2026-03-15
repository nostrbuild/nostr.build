<?php

require_once __DIR__ . '/../imageproc.class.php';
require_once __DIR__ . '/MediaUrlGenerator.php';

/**
 * Handles duplicate detection for uploaded media files.
 *
 * Extracted from MultimediaUpload::checkForDuplicates(). Supports both
 * NIP-96 and Blossom upload protocols with their distinct hash semantics.
 */
class DuplicateDetector
{
    private UploadsData $uploadsData;
    private UploadAttempts $uploadAttempts;
    private S3Service $s3Service;
    private MediaUrlGenerator $urlGenerator;
    private bool $pro;
    private string $userNpub;

    public function __construct(
        UploadsData $uploadsData,
        UploadAttempts $uploadAttempts,
        S3Service $s3Service,
        MediaUrlGenerator $urlGenerator,
        bool $pro,
        string $userNpub
    ) {
        $this->uploadsData = $uploadsData;
        $this->uploadAttempts = $uploadAttempts;
        $this->s3Service = $s3Service;
        $this->urlGenerator = $urlGenerator;
        $this->pro = $pro;
        $this->userNpub = $userNpub;
    }

    /**
     * Check whether a file is a duplicate of an already-uploaded file.
     *
     * @param string $filehash  The (possibly transformed) file hash used for lookup.
     * @param array  $file      The upload file array (must contain 'tmp_name' and 'input_name').
     * @param array  $options   Optional flags:
     *   - 'profile'        => bool   Whether this is a profile picture upload.
     *   - 'blossom'        => bool   Whether this is a Blossom protocol upload.
     *   - 'sha256'         => string The sha256 hash from the Blossom client.
     *   - 'clientInfo'     => string Client request info for webhook data.
     *   - 'no_transform'   => bool   Whether the file was uploaded without transformation.
     *   - 'originalSha256' => string The original sha256 before any transformation.
     *
     * @return array|null  Populated file data array if duplicate, null otherwise.
     */
    public function check(string $filehash, array $file, array $options = []): ?array
    {
        $profile = $options['profile'] ?? false;
        $blossom = $options['blossom'] ?? false;
        $sha256 = $options['sha256'] ?? '';
        $clientInfo = $options['clientInfo'] ?? '';
        $no_transform = $options['no_transform'] ?? false;
        $originalSha256 = $options['originalSha256'] ?? '';

        // Blossom requires duplicate checks using original and/or transformed hashes.
        // When /media endpoint is used, file hash and original hash differ (like nip-96).
        // When /upload endpoint is used, they are the same.
        $data = $this->uploadsData->getUploadData(
            $blossom && !$no_transform ? $originalSha256 : $filehash
        );

        // Record the upload attempt (NIP-96 requirement)
        $this->recordUploadAttempt($filehash);

        if ($data === false) {
            return null;
        }

        $width = $data['media_width'] ?? 0;
        $height = $data['media_height'] ?? 0;
        $blurhash = $data['blurhash'] ?? "LEHV6nWB2yk8pyo0adR*.7kCMdnj"; // Default blurhash
        $blossom_hash = $blossom ? ($data['blossom_hash'] ?? '') : '';

        // For picture type with missing dimensions or default blurhash, recalculate
        if (
            in_array($data['type'], ['picture']) &&
            ($width === 0 || $height === 0 || $blurhash === "LEHV6nWB2yk8pyo0adR*.7kCMdnj")
        ) {
            $img = new ImageProcessor($file['tmp_name']);
            $dimensions = $img->getImageDimensions();
            $blurhash = $img->calculateBlurhash();

            $width = $dimensions['width'];
            $height = $dimensions['height'];

            $this->uploadsData->update(
                $data['id'],
                [
                    'media_width' => $width,
                    'media_height' => $height,
                    'blurhash' => $blurhash,
                ]
            );
        }

        // Get S3 metadata to verify the stored hash
        $stored_object_sha256 = '';
        try {
            $key = $this->urlGenerator->prefix($data['type']) . $data['filename'];
            $fileS3Metadata = $this->s3Service->getObjectMetadataFromR2(
                objectKey: $key,
                mime: $data['mime'],
                paidAccount: $this->pro
            );

            if ($fileS3Metadata === false) {
                throw new \Exception('Failed to get S3 metadata');
            }

            $stored_object_sha256 = $fileS3Metadata['Metadata']['sha256'] ?? '';
        } catch (\Exception $e) {
            error_log("Failed to get S3 metadata: " . $e->getMessage());
            return null;
        }

        // Blossom mode requires a stored sha256 hash to proceed
        if ($blossom && empty($stored_object_sha256)) {
            return null;
        }

        // Blossom no_transform: stored hash must match the client-provided sha256
        if ($blossom && $no_transform && $stored_object_sha256 !== $sha256) {
            return null;
        }

        // Record the blossom hash if it is not already recorded
        if ($blossom && empty($blossom_hash)) {
            $this->uploadsData->update(
                $data['id'],
                [
                    'blossom_hash' => $sha256,
                ]
            );
        }

        // Handle OX data hash mapping:
        // Non-blossom: use $filehash
        // Blossom + no_transform: use $filehash
        // Blossom + transform: use $originalSha256
        $fileDataOX = !$blossom ? $filehash : ($blossom && $no_transform ? $filehash : $originalSha256);

        $fileData = [
            'input_name' => $file['input_name'],
            'name' => $data['filename'],
            'sha256' => $stored_object_sha256,
            'original_sha256' => $fileDataOX, // NIP-96 & Blossom
            'type' => $data['type'],
            'mime' => $fileS3Metadata->get('ContentType'),
            'size' => $data['file_size'],
            'blurhash' => $blurhash,
            'dimensions' => ['width' => $width, 'height' => $height],
            'dimensionsString' => sprintf("%sx%s", $width ?? 0, $height ?? 0),
        ];

        // Profile vs non-profile duplicate distinction
        if ($profile) {
            // A non-profile duplicate does not count as a profile duplicate
            if ($data['type'] !== 'profile') {
                return null;
            }

            error_log("Duplicate profile picture: {$data['filename']}" . PHP_EOL);
            $fileData['url'] = $this->urlGenerator->mediaURL($data['filename'], 'profile');
        } else {
            // A profile duplicate does not count as a non-profile duplicate
            if ($data['type'] === 'profile') {
                return null;
            }

            $fileData['url'] = $this->urlGenerator->mediaURL($data['filename'], $data['type']);
            $fileData['thumbnail'] = $this->urlGenerator->thumbnailURL($data['filename'], $data['type']);
            $fileData['responsive'] = $this->urlGenerator->responsiveURLs($data['filename'], $data['type']);
            $decodedMetadata = json_decode((string)($data['metadata'] ?? ''), true);
            $fileData['metadata'] = is_array($decodedMetadata) ? $decodedMetadata : [];
        }

        // Include data needed by the caller to send webhook notifications
        $fileData['webhook_data'] = [
            'filehash' => $filehash,
            'filename' => $data['filename'],
            'file_size' => $data['file_size'],
            'content_type' => $fileS3Metadata->get('ContentType'),
            'url' => $this->urlGenerator->mediaURL($data['filename'], $data['type']),
            'original_sha256' => $fileDataOX,
            'clientInfo' => $clientInfo,
        ];

        return $fileData;
    }

    /**
     * Record an upload attempt for NIP-96 compliance.
     *
     * Records the filehash associated with the user's npub so that
     * the upload can be found and deleted later by hash.
     */
    public function recordUploadAttempt(string $filehash): void
    {
        if (empty($this->userNpub)) {
            return;
        }

        try {
            $this->uploadAttempts->recordUpload($filehash, $this->userNpub);
        } catch (\Exception $e) {
            error_log("Failed to record upload attempt: " . $e->getMessage());
        }
    }
}
