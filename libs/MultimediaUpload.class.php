<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/S3Service.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/GifConverter.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Account.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UploadsData.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UsersImages.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UsersImagesFolders.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UploadAttempts.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/SiteConfig.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/CloudflareUploadWebhook.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/VideoRepackager.class.php";

// Extracted Upload classes
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/TempFileManager.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/FileInputNormalizer.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/UploadValidator.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/DuplicateDetector.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/MediaProcessor.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/MediaUrlGenerator.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/UploadPersistence.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/Upload/WebhookNotifier.php";

use Psr\Http\Message\StreamInterface;

// Vendor autoload
require_once $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";

/**
 * MultimediaUpload - Facade for the upload pipeline.
 *
 * Delegates to focused classes for each phase of the upload process:
 *   FileInputNormalizer  — normalize input formats
 *   UploadValidator      — pre-upload validation
 *   DuplicateDetector    — Blossom/NIP-96 duplicate detection
 *   MediaProcessor       — image/video transformation
 *   UploadPersistence    — database + S3 storage
 *   MediaUrlGenerator    — CDN URL construction
 *   WebhookNotifier      — post-upload webhook notifications
 *   TempFileManager      — temp file lifecycle
 *
 * The public API is fully backward-compatible with the original monolithic class.
 */
class MultimediaUpload
{
    // -- Delegate instances ---------------------------------------------------
    protected TempFileManager $tempManager;
    protected FileInputNormalizer $normalizer;
    protected UploadValidator $validator;
    protected DuplicateDetector $duplicateDetector;
    protected MediaProcessor $processor;
    protected MediaUrlGenerator $urlGenerator;
    protected UploadPersistence $persistence;
    protected WebhookNotifier $webhookNotifier;

    // -- Core state -----------------------------------------------------------
    protected $db;
    protected array $filesArray = [];
    protected array $uploadedFiles = [];
    protected array $file = [];

    // -- Data layer -----------------------------------------------------------
    protected UploadsData $uploadsData;
    protected UploadAttempts $uploadAttempts;
    protected ?UsersImages $usersImages = null;
    protected ?UsersImagesFolders $usersImagesFolders = null;
    protected S3Service $s3Service;
    protected ?Account $userAccount = null;

    // -- User/account context -------------------------------------------------
    protected string $userNpub;
    protected bool $pro;
    protected ?string $apiClient = null;

    // -- Configuration/metadata -----------------------------------------------
    protected array $uppyMetadata = [];
    protected array $formParams = [];
    protected string $defaultFolderName = '';
    protected bool $no_transform = false;
    protected array $awsConfig = [];

    /**
     * @param mysqli $db
     * @param S3Service $s3Service
     * @param bool $pro
     * @param string $userNpub
     * @param array $awsConfig AWS/R2 config for poster extraction (optional, enables auto poster for pro videos)
     */
    public function __construct(mysqli $db, S3Service $s3Service, bool $pro = false, string $userNpub = '', array $awsConfig = [])
    {
        $this->db = $db;
        $this->s3Service = $s3Service;
        $this->userNpub = $userNpub;
        $this->pro = $pro;
        $this->awsConfig = $awsConfig;

        // Data layer
        $this->uploadsData = new UploadsData($db);
        $this->uploadAttempts = new UploadAttempts($db);
        if ($this->pro) {
            $this->usersImages = new UsersImages($db);
            $this->usersImagesFolders = new UsersImagesFolders($db);
            $this->userAccount = new Account($userNpub, $db);
        }
        if ($this->pro && empty($this->userNpub)) {
            throw new Exception('UserNpub is required for pro uploads');
        }

        // Delegates
        $this->tempManager = new TempFileManager();
        $this->normalizer = new FileInputNormalizer($this->tempManager);
        $this->validator = new UploadValidator($this->uploadsData);
        $this->urlGenerator = new MediaUrlGenerator($pro);
        $this->processor = new MediaProcessor(new GifConverter());
        $this->persistence = new UploadPersistence(
            $s3Service,
            $this->uploadsData,
            $this->usersImages,
            $this->usersImagesFolders,
            $userNpub,
            $pro,
        );
        $this->duplicateDetector = new DuplicateDetector(
            $this->uploadsData,
            $this->uploadAttempts,
            $s3Service,
            $this->urlGenerator,
            $pro,
            $userNpub,
        );
        $this->webhookNotifier = new WebhookNotifier(
            new CloudflareUploadWebhook(
                $_SERVER['NB_API_UPLOAD_SECRET'],
                $_SERVER['NB_API_UPLOAD_INFO_URL'],
            ),
        );
    }

    public function __destruct()
    {
        $this->tempManager->cleanup();
    }

    // =========================================================================
    // File Input Methods (public API preserved)
    // =========================================================================

    public function setFiles(array $files, ?string $tempDirectory = null): void
    {
        $this->filesArray = $this->normalizer->normalizeFiles($files, $tempDirectory);
    }

    public function setRawFiles(array $files): void
    {
        $this->filesArray = $this->normalizer->normalizeRawFiles($files);
    }

    public function setPsrFiles(array $files, mixed $meta = [], ?string $tempDirectory = null): void
    {
        $this->filesArray = $this->normalizer->normalizePsrFiles($files, $meta, $tempDirectory);
    }

    public function setPutFile(StreamInterface $stream, array $metadata = [], ?string $tempDirectory = null): void
    {
        $this->filesArray = $this->normalizer->normalizePutStream('APIv2', $stream, $tempDirectory, $metadata);
    }

    // =========================================================================
    // Result Accessors (public API preserved)
    // =========================================================================

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getFileUrls(): array
    {
        $urls = [];
        foreach ($this->uploadedFiles as $file) {
            $urls[] = $file['url'];
        }
        return $urls;
    }

    // =========================================================================
    // Configuration Setters (public API preserved)
    // =========================================================================

    public function setApiClient($apiClient): self
    {
        $this->apiClient = $apiClient;
        // Rebuild the URL generator with the new apiClient
        $this->urlGenerator = new MediaUrlGenerator($this->pro, $apiClient);
        // Update duplicate detector's URL generator
        $this->duplicateDetector = new DuplicateDetector(
            $this->uploadsData,
            $this->uploadAttempts,
            $this->s3Service,
            $this->urlGenerator,
            $this->pro,
            $this->userNpub,
        );
        return $this;
    }

    public function getApiClient()
    {
        return $this->apiClient;
    }

    public function setFormParams($formParams): self
    {
        $this->formParams = $formParams;
        return $this;
    }

    public function setUppyMetadata(array $uppyMetadata): self
    {
        if (!empty($uppyMetadata['folderHierarchy']) && is_string($uppyMetadata['folderHierarchy'])) {
            $uppyMetadata['folderHierarchy'] = json_decode($uppyMetadata['folderHierarchy'], true);
        }
        $this->no_transform = isset($uppyMetadata['noTransform']) && $uppyMetadata['noTransform'] === 'true';
        $this->uppyMetadata = $uppyMetadata;
        return $this;
    }

    public function setDefaultFolderName(string $folderName): self
    {
        $this->defaultFolderName = $folderName;
        return $this;
    }

    public function getDefaultFolderName(): string
    {
        return $this->defaultFolderName ?? '';
    }

    // =========================================================================
    // Upload Profile Picture
    // =========================================================================

    public function uploadProfilePicture(): array
    {
        if (!is_array($this->filesArray) || empty($this->filesArray)) {
            return [false, 400, 'No files to upload'];
        }

        try {
            $this->file = $this->filesArray[0];
            $fileType = detectFileExt($this->file['tmp_name']);
            if ($fileType['type'] !== 'image' && $fileType['type'] !== 'video') {
                return [false, 400, 'Invalid file type, only images and videos are allowed'];
            }

            // Hash the original file
            $fileSha256 = $this->generateFileHash();
            $this->file['sha256'] = $fileSha256;

            // Check for duplicate profile pictures
            $dupResult = $this->duplicateDetector->check($fileSha256, $this->file, ['profile' => true]);
            if ($dupResult !== null) {
                $this->uploadedFiles[] = $dupResult;
                $this->sendWebhookForDuplicate($dupResult, $fileSha256);
                error_log('Duplicate file:' . $fileSha256 . PHP_EOL);
                return [true, 200, 'Upload successful.'];
            }

            // Validate
            $validationResult = $this->validator->validate($this->file, $this->pro, $this->no_transform, $this->userAccount, $this->userNpub);
            if ($validationResult[0] !== true) {
                return $validationResult;
            }

            // Process the profile image
            $fileData = $this->processor->processImage($this->file['tmp_name'], $fileType, 'profile', false, $this->file['size']);
            if ($fileData['newTmpPath'] !== null) {
                $this->tempManager->replace($this->file, $fileData['newTmpPath'], true);
            }

            // Re-detect file type after processing
            $fileType = detectFileExt($this->file['tmp_name']);

            $newFileName = $fileSha256 . '.' . $fileType['extension'];
            $newFilePrefix = $this->urlGenerator->prefix('profile');
            $newFileSize = filesize($this->file['tmp_name']);

            // Store in database
            $insert_id = $this->persistence->storeFree(
                $newFileName,
                json_encode($fileData['metadata'] ?? []),
                $newFileSize,
                'profile',
                (int)($fileData['dimensions']['width'] ?? 0),
                (int)($fileData['dimensions']['height'] ?? 0),
                $fileData['blurhash'] ?? null,
                $fileType['mime'] ?? null,
            );
            if ($insert_id === false) {
                error_log('Failed to insert file into database');
                return [false, 500, 'Server error, please try again later'];
            }

            // Upload to S3
            if (!$this->persistence->uploadToS3($this->file['tmp_name'], $newFilePrefix . $newFileName, $fileSha256)) {
                error_log('Failed to upload file to S3');
                return [false, 500, 'Server error, please try again later'];
            }

            // Build response
            $currentSha256 = $this->generateFileHash();
            $this->uploadedFiles[] = [
                'input_name' => $this->file['input_name'],
                'name' => $newFileName,
                'url' => $this->urlGenerator->mediaURL($newFileName, 'profile'),
                'sha256' => $currentSha256,
                'original_sha256' => $fileSha256,
                'type' => 'profile',
                'mime' => $fileType['mime'],
                'size' => $newFileSize,
                'dimensions' => $fileData['dimensions'] ?? [],
                'dimensionsString' => isset($fileData['dimensions']) ? sprintf('%dx%d', $fileData['dimensions']['width'] ?? 0, $fileData['dimensions']['height'] ?? 0) : '0x0',
                'blurhash' => $fileData['blurhash'] ?? '',
            ];
        } catch (Exception $e) {
            error_log("Profile picture upload failed: " . $e->getMessage());
            return [false, 500, 'Server error, please try again later'];
        }

        // Cleanup temp file
        if (!empty($this->file['tmp_name']) && file_exists($this->file['tmp_name'])) {
            unlink($this->file['tmp_name']);
            $this->tempManager->release($this->file['tmp_name']);
        }

        // Webhook notification
        $this->sendUploadWebhook(
            $fileSha256,
            $newFileName,
            $newFileSize,
            $fileType['mime'],
            $this->urlGenerator->mediaURL($newFileName, 'profile'),
            $fileType['type'],
            $fileSha256,
            $currentSha256,
            false,
        );

        return [true, 200, 'Profile picture uploaded successfully'];
    }

    // =========================================================================
    // Upload Files (batch)
    // =========================================================================

    public function uploadFiles(bool $no_transform = false, ?bool $blossom = false, ?string $sha256 = '', ?string $clientInfo = ''): array
    {
        if ($this->no_transform !== true) {
            $this->no_transform = $no_transform;
        }

        if (!is_array($this->filesArray) || empty($this->filesArray)) {
            return [false, 400, 'No files to upload'];
        }

        $lastError = null;
        $returnError = [];
        $successfulUploads = 0;

        $baseNoTransform = $this->no_transform;
        foreach ($this->filesArray as $file) {
            try {
                $this->file = $file;
                $this->no_transform = $baseNoTransform; // Reset per file to prevent cross-contamination

                // Hash original file
                $fileSha256 = $this->generateFileHash();
                $this->file['sha256'] = $fileSha256;

                // Blossom hash verification
                if ($blossom && $sha256 && $fileSha256 !== $sha256) {
                    error_log('File hash mismatch: ' . $fileSha256 . ' != ' . $sha256);
                    return [false, 400, 'File hash mismatch'];
                }

                // Check duplicates (non-Blossom, non-pro)
                if (!$this->pro && !$blossom) {
                    $dupResult = $this->duplicateDetector->check($fileSha256, $this->file);
                    if ($dupResult !== null) {
                        $this->uploadedFiles[] = $dupResult;
                        $this->sendWebhookForDuplicate($dupResult, $fileSha256);
                        error_log('Duplicate file:' . $fileSha256 . PHP_EOL);
                        continue;
                    }
                }

                // Validate
                $validationResult = $this->validator->validate($this->file, $this->pro, $this->no_transform, $this->userAccount, $this->userNpub);
                if ($validationResult[0] !== true) {
                    $returnError[] = $validationResult;
                    throw new Exception('File validation failed: ' . json_encode($validationResult));
                }

                // Get account level for file type detection
                $accountLevelInt = 0;
                try {
                    if ($this->userAccount) {
                        $accountLevelInt = $this->userAccount->getAccountLevelInt();
                    }
                } catch (Exception $e) {
                    error_log("Failed to get account level: " . $e->getMessage());
                }

                // Detect file type
                $fileType = detectFileExt($this->file['tmp_name'], $accountLevelInt);

                // Process images
                if ($fileType['type'] === 'image') {
                    $tier = $this->pro ? 'pro' : 'free';
                    $fileData = $this->processor->processImage($this->file['tmp_name'], $fileType, $tier, $this->no_transform, $this->file['size']);

                    // Handle temp file replacement from GIF downsizing
                    if ($fileData['newTmpPath'] !== null) {
                        $this->tempManager->replace($this->file, $fileData['newTmpPath'], true);
                    }
                    if ($fileData['noTransformOverride']) {
                        $this->no_transform = true;
                    }

                    // Check for private metadata when no_transform
                    if (!empty($fileData) && $this->no_transform && !empty($fileData['privateMetadata'])) {
                        $returnError[] = [false, 400, 'Private metadata detected: ' . json_encode($fileData['privateMetadata'])];
                        throw new Exception('Private metadata detected');
                    }

                    // Re-detect after processing
                    $fileType = detectFileExt($this->file['tmp_name'], $accountLevelInt);
                } else {
                    $fileData = [];
                }

                // Repackage video
                if ($fileType['type'] === 'video') {
                    $newVideoPath = $this->processor->processVideo($this->file['tmp_name'], $this->file['size'], $this->no_transform);
                    if ($newVideoPath !== null) {
                        $this->tempManager->replace($this->file, $newVideoPath, true);
                    }
                    $fileType = detectFileExt($this->file['tmp_name'], $accountLevelInt);
                }

                // Generate transformed file hash
                $transformedFileSha256 = $this->generateFileHash();

                // Check dupes for blossom uploads (with transformed hash)
                if (!$this->pro && $blossom) {
                    $dupResult = $this->duplicateDetector->check($transformedFileSha256, $this->file, [
                        'blossom' => true,
                        'sha256' => $sha256,
                        'clientInfo' => $clientInfo,
                        'no_transform' => $this->no_transform,
                        'originalSha256' => $fileSha256,
                    ]);
                    if ($dupResult !== null) {
                        $this->uploadedFiles[] = $dupResult;
                        $this->sendWebhookForDuplicate($dupResult, $fileSha256);
                        error_log('Duplicate file:' . $fileSha256 . PHP_EOL);
                        continue;
                    }
                }

                // DB-compatible file type
                $newFileType = match ($fileType['type']) {
                    'image' => 'picture',
                    'video' => 'video',
                    'audio' => 'video',
                    default => $fileType['type'],
                };

                // Store in database and upload to S3
                if (!$this->pro) {
                    $newFileName = $fileSha256 . '.' . $fileType['extension'];
                    $newFilePrefix = $this->urlGenerator->prefix($fileType['type']);
                    $newFileSize = filesize($this->file['tmp_name']);

                    $insert_id = $this->persistence->storeFree(
                        $newFileName,
                        json_encode($fileData['metadata'] ?? []),
                        $newFileSize,
                        $newFileType,
                        (int)($fileData['dimensions']['width'] ?? 0),
                        (int)($fileData['dimensions']['height'] ?? 0),
                        $fileData['blurhash'] ?? null,
                        $fileType['mime'] ?? null,
                        $blossom && $no_transform ? $sha256 : ($blossom ? $transformedFileSha256 : null),
                    );
                    if ($insert_id === false) {
                        $returnError[] = [false, 500, 'Failed to insert into database'];
                        throw new Exception('Failed to insert into database');
                    }
                } else {
                    // Pro: insert placeholder, generate name from ID, then update
                    $insert_id = $this->persistence->storePro($fileSha256);
                    if ($insert_id === false) {
                        $returnError[] = [false, 500, 'Failed to insert into database'];
                        throw new Exception('Failed to insert into database');
                    }
                    $fileData['insert_id'] = $insert_id;

                    $newFileName = getUniqueNanoId() . '.' . $fileType['extension'];
                    $newFilePrefix = $this->urlGenerator->prefix($fileType['type']);
                    $newFileSize = filesize($this->file['tmp_name']);

                    $originalFileName = substr($this->file['name'], 0, 255);
                    if (!$this->persistence->updatePro(
                        $insert_id,
                        $newFileName,
                        $newFileSize,
                        $fileData['dimensions']['width'] ?? 0,
                        $fileData['dimensions']['height'] ?? 0,
                        $fileData['blurhash'] ?? null,
                        $fileType['mime'],
                        !empty($file['title']) ? $file['title'] : $originalFileName,
                        !empty($file['ai_prompt']) ? $file['ai_prompt'] : '',
                        $blossom && $no_transform ? $sha256 : ($blossom ? $transformedFileSha256 : null),
                        $this->uppyMetadata,
                        $this->defaultFolderName,
                    )) {
                        $returnError[] = [false, 500, 'Failed to update database'];
                        throw new Exception('Failed to update database');
                    }
                }

                if (!$this->persistence->uploadToS3($this->file['tmp_name'], $newFilePrefix . $newFileName, $fileSha256)) {
                    $returnError[] = [false, 500, 'Failed to upload to S3'];
                    throw new Exception('Upload to S3 failed');
                }

                // Auto-extract video poster for pro uploads (best-effort, never fails the upload)
                if ($this->pro && $fileType['type'] === 'video' && !empty($this->awsConfig)) {
                    try {
                        require_once __DIR__ . '/VideoPosterExtractor.class.php';
                        $posterExtractor = new VideoPosterExtractor($this->awsConfig, $this->usersImages);
                        $posterExtractor->extractAndUpload(
                            $this->file['tmp_name'],
                            $newFileName,
                            $insert_id,
                            $this->userNpub
                        );
                    } catch (\Throwable $e) {
                        error_log("Auto poster extraction failed: " . $e->getMessage());
                    }
                }

                // Build response
                $this->uploadedFiles[] = [
                    'id' => $fileData['insert_id'] ?? 0,
                    'input_name' => $this->file['input_name'],
                    'name' => $newFileName,
                    'url' => $this->urlGenerator->mediaURL($newFileName, $fileType['type']),
                    'thumbnail' => $this->urlGenerator->thumbnailURL($newFileName, $fileType['type']),
                    'responsive' => $this->urlGenerator->responsiveURLs($newFileName, $fileType['type']),
                    'blurhash' => $fileData['blurhash'] ?? '',
                    'sha256' => $transformedFileSha256,
                    'original_sha256' => $fileSha256,
                    'type' => $newFileType,
                    'media_type' => $fileType['type'],
                    'mime' => $fileType['mime'],
                    'size' => $newFileSize,
                    'metadata' => $fileData['metadata'] ?? [],
                    'dimensions' => $fileData['dimensions'] ?? [],
                    'dimensionsString' => isset($fileData['dimensions']) ? sprintf('%dx%d', $fileData['dimensions']['width'] ?? 0, $fileData['dimensions']['height'] ?? 0) : '0x0',
                ];
                $successfulUploads++;
            } catch (Exception $e) {
                $lastError = $e;
                error_log("File loop exception: " . $lastError->getMessage());
                continue;
            }

            // Webhook notification for successful upload
            $originalMediaUrl = $this->urlGenerator->mediaURL($newFileName, $fileType['type']);
            $doVirusScan = in_array($fileType['type'], ['archive', 'document', 'text', 'other'], true);
            $this->sendUploadWebhook(
                explode('.', $newFileName)[0],
                $newFileName,
                $newFileSize,
                $fileType['mime'],
                $originalMediaUrl,
                $fileType['type'],
                $fileSha256,
                $transformedFileSha256,
                $doVirusScan,
                $blossom && !empty($clientInfo) ? $clientInfo : null,
            );
        }

        // Determine return status
        if ($successfulUploads === 0 && ($lastError !== null || $returnError !== [])) {
            if ($lastError !== null && $returnError === []) {
                return [false, 500, 'Server error, please try again later'];
            } else {
                return $returnError[0];
            }
        } elseif ($successfulUploads > 0 && $returnError !== []) {
            return [true, 200, 'Some files failed to upload'];
        }

        return [true, 200, "Upload successful."];
    }

    // =========================================================================
    // Upload from URL
    // =========================================================================

    public function uploadFileFromUrl(string $url, bool $pfp = false, ?string $title = '', ?string $ai_prompt = '', ?bool $no_transform = false, ?bool $blossom = false, ?string $sha256 = '', ?string $clientInfo = ''): array
    {
        $sizeLimit = $this->pro
            ? $this->userAccount->getPerFileUploadLimit()
            : SiteConfig::FREE_UPLOAD_LIMIT;

        // Get URL metadata
        try {
            $metadata = $this->getUrlMetadata($url);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [false, 400, $e->getMessage()];
        }

        // Validate type
        list($fileType) = explode("/", $metadata['type'], 2);
        if (!in_array($fileType, ['image', 'video', 'audio'], true)) {
            error_log('Invalid file type: ' . $fileType);
            return [false, 400, 'Invalid file type'];
        }

        // Validate size
        if (intval($metadata['size']) > $sizeLimit) {
            error_log('File size exceeds the limit of ' . formatSizeUnits($sizeLimit));
            return [false, 413, 'File size exceeds the limit of ' . formatSizeUnits($sizeLimit)];
        }

        // Download the file
        $ch = curl_init($url);
        if ($ch === false) {
            error_log('Failed to initialize cURL');
            return [false, 500, 'Server error, please try again later'];
        }

        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $tempFile = tempnam(sys_get_temp_dir(), 'url_download');
        if ($tempFile === false) {
            error_log('Failed to create temporary file');
            $ch = null;
            return [false, 500, 'Server error, please try again later'];
        }
        $fp = fopen($tempFile, 'wb');

        if ($fp === false) {
            error_log('Failed to open file for writing');
            $ch = null;
            @unlink($tempFile);
            return [false, 500, 'Server error, please try again later'];
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);

        // Size limit enforcement during download
        $fileSize = 0;
        $curlSizeExceeded = false;
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) use (&$fileSize, $sizeLimit, &$curlSizeExceeded) {
            $fileSize = $downloaded;
            if ($fileSize > $sizeLimit) {
                $curlSizeExceeded = true;
                return 1;
            }
            return 0;
        });

        $success = curl_exec($ch);
        fclose($fp);

        if ($success === false) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
            $this->tempManager->release($tempFile);
            if ($fileSize > $sizeLimit || $curlSizeExceeded) {
                error_log('File size exceeds the limit of ' . formatSizeUnits($sizeLimit));
                $ch = null;
                return [false, 413, 'File size exceeds the limit of ' . formatSizeUnits($sizeLimit)];
            }
            error_log('cURL error: ' . curl_error($ch));
            $ch = null;
            return [false, 500, 'Server error, please try again later'];
        }

        $ch = null;

        $resolvedTmpPath = realpath($tempFile);
        $resolvedTmpPath = is_string($resolvedTmpPath) && $resolvedTmpPath !== '' ? $resolvedTmpPath : $tempFile;

        $this->filesArray[] = [
            'input_name' => 'url',
            'name' => $metadata['name'],
            'type' => $metadata['type'],
            'tmp_name' => $resolvedTmpPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tempFile),
            'title' => $title ?? '',
            'ai_prompt' => $ai_prompt ?? '',
        ];
        $this->tempManager->register($resolvedTmpPath);

        if ($pfp) {
            return $this->uploadProfilePicture();
        } else {
            return $this->uploadFiles(
                no_transform: $no_transform,
                blossom: $blossom,
                sha256: $sha256,
                clientInfo: $clientInfo,
            );
        }
    }

    /**
     * Fetch metadata (size, type, name) from a URL via HEAD request.
     */
    public function getUrlMetadata(string $url): array
    {
        try {
            $sanity = checkUrlSanity($url);
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw new Exception($e->getMessage());
        }

        if (!$sanity) {
            throw new Exception('URL sanity check failed');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $headers = curl_exec($ch);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            $ch = null;
            throw new Exception('Curl error: ' . $curlError);
        }

        if (!is_string($headers) || $headers === '') {
            $ch = null;
            throw new Exception('Failed to read URL metadata headers');
        }

        $headerList = explode("\n", $headers);
        $headerMap = [];
        foreach ($headerList as $header) {
            $parts = explode(": ", $header, 2);
            if (count($parts) === 2) {
                $headerName = strtolower($parts[0]);
                $headerMap[$headerName] = trim($parts[1]);
            }
        }

        $ch = null;

        if (!isset($headerMap['content-length'])) {
            throw new Exception('Unable to determine file size');
        }

        if (!isset($headerMap['content-type'])) {
            throw new Exception('Unable to determine file type');
        }

        return [
            'name' => basename($url),
            'type' => $headerMap['content-type'],
            'size' => $headerMap['content-length'],
        ];
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Generate SHA256 hash of the current file.
     */
    protected function generateFileHash(): string
    {
        $tmpName = is_array($this->file) ? (string)($this->file['tmp_name'] ?? '') : '';
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new RuntimeException('Unable to generate file hash: temporary file is missing');
        }

        $hash = hash_file('sha256', $tmpName);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Unable to generate file hash');
        }

        return $hash;
    }

    /**
     * Send webhook notification for a new upload.
     */
    private function sendUploadWebhook(
        string $fileHash,
        string $fileName,
        int $fileSize,
        string $fileMimeType,
        string $fileUrl,
        string $fileType,
        string $originalSha256,
        string $currentSha256,
        bool $doVirusScan,
        ?string $clientInfoOverride = null,
    ): void {
        $mimeRoot = explode('/', $fileMimeType)[0] ?? 'other';
        $whFileType = match ($mimeRoot) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            default => $fileType,
        };

        $this->webhookNotifier->notify(WebhookNotifier::buildParams(
            fileHash: $fileHash,
            fileName: $fileName,
            fileSize: $fileSize,
            fileMimeType: $fileMimeType,
            fileUrl: $fileUrl,
            fileType: $whFileType,
            shouldTranscode: false,
            uploadAccountType: $this->pro ? 'subscriber' : 'free',
            uploadedFileInfo: $clientInfoOverride ?? $_SERVER['CLIENT_REQUEST_INFO'] ?? null,
            uploadNpub: $this->userNpub ?? null,
            fileOriginalUrl: null,
            originalSha256Hash: $originalSha256,
            currentSha256Hash: $currentSha256,
            doVirusScan: $doVirusScan,
        ));
    }

    /**
     * Send webhook notification when a duplicate is detected.
     */
    private function sendWebhookForDuplicate(array $dupResult, string $fileSha256): void
    {
        $webhookData = $dupResult['webhook_data'] ?? null;
        if ($webhookData === null) {
            return;
        }

        $mimeRoot = explode('/', (string)($webhookData['content_type'] ?? 'application/octet-stream'))[0] ?? 'other';
        $whFileType = match ($mimeRoot) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            default => 'other',
        };

        $this->webhookNotifier->notify(WebhookNotifier::buildParams(
            fileHash: $webhookData['filehash'],
            fileName: $webhookData['filename'],
            fileSize: $webhookData['file_size'],
            fileMimeType: $webhookData['content_type'],
            fileUrl: $webhookData['url'],
            fileType: $whFileType,
            shouldTranscode: false,
            uploadAccountType: $this->pro ? 'subscriber' : 'free',
            uploadedFileInfo: $webhookData['clientInfo'] ?? $_SERVER['CLIENT_REQUEST_INFO'] ?? null,
            uploadNpub: $this->userNpub ?? null,
            fileOriginalUrl: null,
            originalSha256Hash: $webhookData['original_sha256'] ?? $fileSha256,
            currentSha256Hash: $dupResult['sha256'] ?? $fileSha256,
            doVirusScan: false,
        ));
    }
}
