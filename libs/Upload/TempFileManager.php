<?php

/**
 * TempFileManager - RAII-style temporary file lifecycle management.
 *
 * Tracks temporary file paths and ensures they are cleaned up when the manager
 * is destroyed. Extracted from MultimediaUpload to provide reusable temp file
 * management with deterministic cleanup via __destruct().
 *
 * Usage:
 *   $tfm = new TempFileManager();
 *   $tfm->register('/tmp/upload_abc123');
 *   // ... work with the file ...
 *   // Files are automatically deleted when $tfm goes out of scope,
 *   // or explicitly via $tfm->cleanup().
 */
class TempFileManager
{
    /**
     * Map of tracked temporary file paths to a boolean flag.
     * Keys are absolute file paths; values are always true.
     *
     * @var array<string, bool>
     */
    private array $managedFiles = [];

    /**
     * Destructor - automatically cleans up all tracked temporary files.
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Register a temporary file path for tracking.
     *
     * The file will be deleted when cleanup() is called or when this
     * manager is destroyed. Empty paths are silently ignored.
     *
     * @param string $path Absolute path to the temporary file.
     */
    public function register(string $path): void
    {
        if ($path !== '') {
            $this->managedFiles[$path] = true;
        }
    }

    /**
     * Remove a temporary file path from tracking without deleting it.
     *
     * Use this when a temp file has been moved or promoted to a permanent
     * location and should no longer be cleaned up.
     *
     * @param string $path Absolute path to release from tracking.
     */
    public function release(string $path): void
    {
        if ($path !== '' && isset($this->managedFiles[$path])) {
            unset($this->managedFiles[$path]);
        }
    }

    /**
     * Delete all tracked temporary files and clear the tracking list.
     *
     * Silently ignores files that no longer exist on disk. Suppresses
     * unlink errors (e.g. permission issues) to avoid disrupting
     * shutdown sequences.
     */
    public function cleanup(): void
    {
        foreach (array_keys($this->managedFiles) as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
            unset($this->managedFiles[$path]);
        }
    }

    /**
     * Register the tmp_name paths from an array of raw uploaded file arrays.
     *
     * Iterates over a list of file arrays (as provided by $_FILES normalization)
     * and registers each valid tmp_name for cleanup. Entries that are not arrays
     * or lack a valid tmp_name are silently skipped.
     *
     * @param array<int, array{tmp_name?: string}> $files Array of file arrays,
     *        each expected to contain a 'tmp_name' key.
     */
    public function trackRawFilesTempPaths(array $files): void
    {
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $tmpName = $file['tmp_name'] ?? '';
            if (is_string($tmpName) && $tmpName !== '' && is_file($tmpName)) {
                $this->register($tmpName);
            }
        }
    }

    /**
     * Replace a file array's tmp_name with a new path, managing cleanup of both.
     *
     * Updates the referenced file array's 'tmp_name' to point to $newPath.
     * The old tmp_name is optionally deleted from disk and released from tracking.
     * The new path is registered for tracking if it exists on disk.
     *
     * @param array{tmp_name?: string} &$file    File array (modified by reference).
     * @param string                   $newPath   New temporary file path to swap in.
     * @param bool                     $deleteOld Whether to delete the old file from
     *                                            disk (default: true).
     */
    public function replace(array &$file, string $newPath, bool $deleteOld = true): void
    {
        $oldPath = is_array($file) ? (string)($file['tmp_name'] ?? '') : '';

        if ($oldPath !== '' && $oldPath !== $newPath) {
            if ($deleteOld && is_file($oldPath)) {
                @unlink($oldPath);
            }
            $this->release($oldPath);
        }

        $file['tmp_name'] = $newPath;
        if ($newPath !== '' && is_file($newPath)) {
            $this->register($newPath);
        }
    }

    /**
     * Check whether a given path is currently being tracked.
     *
     * @param string $path Absolute path to check.
     * @return bool True if the path is tracked, false otherwise.
     */
    public function isTracked(string $path): bool
    {
        return isset($this->managedFiles[$path]);
    }

    /**
     * Return the number of currently tracked temporary files.
     *
     * @return int Count of tracked paths.
     */
    public function count(): int
    {
        return count($this->managedFiles);
    }
}
