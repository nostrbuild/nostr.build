<?php

require_once __DIR__ . '/TempFileManager.php';
require_once __DIR__ . '/../utils.funcs.php';

use Psr\Http\Message\StreamInterface;

/**
 * Normalizes various file input formats into a uniform array structure.
 *
 * Extracted from MultimediaUpload class. Handles:
 * - Traditional $_FILES arrays (single and multi-file)
 * - PSR-7 UploadedFileInterface instances
 * - PUT request streams
 * - Pre-structured raw file arrays
 *
 * Each normalized entry contains:
 *   'input_name' => string,
 *   'name'       => string,
 *   'type'       => string,
 *   'tmp_name'   => string,
 *   'error'      => int,
 *   'size'       => int,
 *   'metadata'   => array (optional)
 */
class FileInputNormalizer
{
    private TempFileManager $tempManager;

    public function __construct(TempFileManager $tempManager)
    {
        $this->tempManager = $tempManager;
    }

    /**
     * Normalize a traditional $_FILES array into a flat list of file entries.
     *
     * Handles both single-file and multi-file input fields. Each uploaded file
     * is moved from PHP's temporary location to a unique path in the given
     * temp directory via move_uploaded_file().
     *
     * @param array $files      The $_FILES superglobal or equivalent array.
     * @param string|null $tempDir  Directory to store temp files (defaults to sys_get_temp_dir()).
     * @return array  Flat array of normalized file entries.
     */
    public function normalizeFiles(array $files, ?string $tempDir = null): array
    {
        $tempDir = $tempDir ?? sys_get_temp_dir();
        $restructured = [];

        foreach ($files as $fileInputName => $fileArray) {
            if (!is_array($fileArray) || !isset($fileArray['name'], $fileArray['tmp_name'], $fileArray['error'], $fileArray['size'])) {
                continue;
            }

            if (is_array($fileArray['name'])) {
                $fileCount = count($fileArray['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if (!isset($fileArray['tmp_name'][$i])) {
                        continue;
                    }
                    $tempFilePath = generateUniqueFilename('file_upload_', $tempDir);
                    if (move_uploaded_file($fileArray['tmp_name'][$i], $tempFilePath)) {
                        $this->tempManager->register($tempFilePath);
                        $restructured[] = [
                            'input_name' => $fileInputName,
                            'name' => $fileArray['name'][$i] ?? basename($tempFilePath),
                            'type' => $fileArray['type'][$i] ?? 'application/octet-stream',
                            'tmp_name' => $tempFilePath,
                            'error' => $fileArray['error'][$i] ?? UPLOAD_ERR_OK,
                            'size' => $fileArray['size'][$i] ?? 0,
                        ];
                    }
                }
            } else {
                $tempFilePath = generateUniqueFilename('file_upload_', $tempDir);
                if (move_uploaded_file($fileArray['tmp_name'], $tempFilePath)) {
                    $this->tempManager->register($tempFilePath);
                    $restructured[] = [
                        'input_name' => $fileInputName,
                        'name' => $fileArray['name'] ?? basename($tempFilePath),
                        'type' => $fileArray['type'] ?? 'application/octet-stream',
                        'tmp_name' => $tempFilePath,
                        'error' => $fileArray['error'] ?? UPLOAD_ERR_OK,
                        'size' => $fileArray['size'] ?? 0,
                    ];
                }
            }
        }

        return $restructured;
    }

    /**
     * Normalize PSR-7 UploadedFileInterface instances into a flat list of file entries.
     *
     * Each file is moved to the temp directory via UploadedFileInterface::moveTo().
     *
     * @param array $files      Array of UploadedFileInterface instances (may be nested).
     * @param mixed $meta       Metadata array or value associated with the files.
     * @param string|null $tempDir  Directory to store temp files (defaults to sys_get_temp_dir()).
     * @return array  Flat array of normalized file entries.
     */
    public function normalizePsrFiles(array $files, mixed $meta = [], ?string $tempDir = null): array
    {
        $tempDir = $tempDir ?? sys_get_temp_dir();
        $restructured = [];

        foreach ($files as $index => $file) {
            // check if $file is an array and handle accordingly
            if (is_array($file)) {
                foreach ($file as $i => $individualFile) {
                    // The $individualFile here is an instance of UploadedFileInterface
                    $fileMeta = isset($meta[$index]) ? $meta[$index] : null;
                    $restructured[] = $this->handlePsrUploadedFile('APIv2', $individualFile, $tempDir, $fileMeta);
                }
            } else {
                // The $file here is an instance of UploadedFileInterface
                $fileMeta = is_array($meta) ? $meta : [];
                $restructured[] = $this->handlePsrUploadedFile('APIv2', $file, $tempDir, $fileMeta);
            }
        }

        return $restructured;
    }

    /**
     * Handle a single PSR-7 UploadedFileInterface instance.
     *
     * @param string $fileInputName  Logical input name for the file entry.
     * @param mixed  $file           UploadedFileInterface instance.
     * @param string $tempDirectory  Directory to store the temp file.
     * @param mixed  $metadata       Metadata associated with this file.
     * @return array  Normalized file entry.
     */
    private function handlePsrUploadedFile(string $fileInputName, mixed $file, string $tempDirectory, mixed $metadata): array
    {
        $tempFilePath = generateUniqueFilename('file_upload_', $tempDirectory);

        // Move the file to the temporary directory
        $file->moveTo($tempFilePath);
        $this->tempManager->register($tempFilePath);

        return [
            'input_name' => $fileInputName,
            'name' => $file->getClientFilename() ?? basename($tempFilePath),
            'type' => $file->getClientMediaType() ?? 'application/octet-stream',
            'tmp_name' => $tempFilePath,
            'error' => $file->getError(),
            'size' => (int)($file->getSize() ?? 0),
            'metadata' => $metadata, // Include the metadata for the file
        ];
    }

    /**
     * Normalize a PUT request stream into a file entry array.
     *
     * Reads the stream contents and writes them to a temporary file, respecting
     * an optional content_length limit from metadata.
     *
     * @param string          $inputName   Logical input name for the file entry.
     * @param StreamInterface $stream      The PUT request body stream.
     * @param string|null     $tempDir     Directory to store the temp file (defaults to sys_get_temp_dir()).
     * @param array           $metadata    Metadata including optional 'filename', 'content_type', 'content_length'.
     * @return array  Array containing a single normalized file entry.
     */
    public function normalizePutStream(string $inputName, StreamInterface $stream, ?string $tempDir = null, array $metadata = []): array
    {
        $tempDir = $tempDir ?? sys_get_temp_dir();
        $tempFilePath = generateUniqueFilename('file_upload_', $tempDir);

        // Write stream to temporary file
        $handle = fopen($tempFilePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary file for PUT upload');
        }
        $this->tempManager->register($tempFilePath);

        $contentLength = isset($metadata['content_length']) ? (int)$metadata['content_length'] : PHP_INT_MAX;
        $size = 0;

        while (!$stream->eof() && $size < $contentLength) {
            $chunk = $stream->read(8192);
            $size += strlen($chunk);
            fwrite($handle, $chunk);
        }

        fclose($handle);

        // Get filename from metadata or generate one
        $filename = $metadata['filename'] ?? basename($tempFilePath);

        return [[
            'input_name' => $inputName,
            'name' => $filename,
            'type' => $metadata['content_type'] ?? 'application/octet-stream',
            'tmp_name' => $tempFilePath,
            'error' => 0,
            'size' => $size,
            'metadata' => $metadata,
        ]];
    }

    /**
     * Pass through pre-structured file arrays, tracking their temp paths for cleanup.
     *
     * Use this when files have already been normalized externally but still need
     * temp file lifecycle management.
     *
     * @param array $files  Array of file entries, each with at least a 'tmp_name' key.
     * @return array  The same array, unmodified.
     */
    public function normalizeRawFiles(array $files): array
    {
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $tmpName = $file['tmp_name'] ?? '';
            if (is_string($tmpName) && $tmpName !== '' && is_file($tmpName)) {
                $this->tempManager->register($tmpName);
            }
        }

        return $files;
    }
}
