<?php

declare(strict_types=1);

namespace DocxMerge\Zip;

use DocxMerge\Exception\InvalidSourceException;

/**
 * Provides helper methods for safe ZIP archive operations.
 *
 * Handles path sanitization to prevent directory traversal attacks,
 * and wraps common ZipArchive operations with proper error handling
 * and typed exceptions.
 */
final class ZipHelper
{
    /**
     * Validates that a ZIP entry path is safe and relative.
     *
     * Rejects paths containing parent directory traversal sequences
     * (../ or ..\) and absolute paths starting with a forward slash.
     *
     * @param string $path The ZIP entry path to validate.
     *
     * @throws InvalidSourceException If the path contains traversal sequences or is absolute.
     */
    public function sanitizePath(string $path): void
    {
        // Normalize backslashes to forward slashes for consistent checking on all platforms.
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/')) {
            throw new InvalidSourceException(
                "Absolute paths are not allowed in ZIP entries: {$path}"
            );
        }

        if (str_contains($normalized, '../')) {
            throw new InvalidSourceException(
                "Path traversal is not allowed in ZIP entries: {$path}"
            );
        }
    }
}
