<?php

declare(strict_types=1);

/**
 * Integration test for custom marker pattern end-to-end.
 *
 * Verifies that the full merge pipeline works with non-default marker
 * delimiters (double-brace {{MARKER}} instead of ${MARKER}).
 */

use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeOptions;

describe('Custom marker pattern', function (): void {
    /** @var string $output */
    $output = '';

    afterEach(function () use (&$output): void {
        if ($output !== '' && file_exists($output)) {
            unlink($output);
        }
        $output = '';
    });

    it('merges with custom marker pattern end-to-end', function () use (&$output): void {
        // Arrange
        $output = tempnam(sys_get_temp_dir(), 'docx_custom_') . '.docx';
        $merger = new DocxMerger();

        // Act
        $result = $merger->merge(
            templatePath: fixture('template-custom-pattern.docx'),
            merges: ['CONTENT' => fixture('source-simple.docx')],
            outputPath: $output,
            options: new MergeOptions(markerPattern: '/\{\{([A-Z_]+)\}\}/'),
        );

        // Assert
        expect($result->success)->toBeTrue();
        expect(file_exists($output))->toBeTrue();

        $zip = new ZipArchive();
        expect($zip->open($output))->toBe(true);

        $docXml = $zip->getFromName('word/document.xml');
        expect($docXml)->not->toBeFalse();
        assert(is_string($docXml));

        // Custom marker should be removed
        expect($docXml)->not->toContain('{{CONTENT}}');
        // Source content should be present
        expect($docXml)->toContain('Hello from source');

        $zip->close();
    });
});
