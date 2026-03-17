<?php

declare(strict_types=1);

/**
 * Tests for ZipHelper.
 *
 * Verifies path sanitization against traversal attacks and
 * error handling for ZIP operations.
 */

use DocxMerge\Exception\InvalidSourceException;
use DocxMerge\Zip\ZipHelper;

describe('ZipHelper', function (): void {
    describe('sanitizePath()', function (): void {
        it('throws for paths containing parent directory traversal', function (): void {
            // Arrange
            $helper = new ZipHelper();

            // Act + Assert
            expect(fn () => $helper->sanitizePath('../etc/passwd'))
                ->toThrow(InvalidSourceException::class);
        });

        it('throws for paths starting with forward slash', function (): void {
            // Arrange
            $helper = new ZipHelper();

            // Act + Assert
            expect(fn () => $helper->sanitizePath('/absolute/path'))
                ->toThrow(InvalidSourceException::class);
        });

        it('accepts valid relative paths', function (): void {
            // Arrange
            $helper = new ZipHelper();

            // Act + Assert -- should not throw
            $helper->sanitizePath('word/document.xml');
            $helper->sanitizePath('word/media/image1.png');
            expect(true)->toBeTrue(); // No exception means pass
        });
    });
});
