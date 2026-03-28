<?php

declare(strict_types=1);

/**
 * Integration tests for MergeOrchestrator reprocessing behavior.
 *
 * Verifies that the merge pipeline correctly uses an existing output file
 * as the working base when isReprocessing is enabled, and falls back to the
 * original template when the output does not exist or reprocessing is disabled.
 */

use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeOptions;

describe('MergeOrchestrator reprocessing', function (): void {
    /** @var string $output */
    $output = '';

    afterEach(function () use (&$output): void {
        if ($output !== '' && file_exists($output)) {
            unlink($output);
        }
        $output = '';
    });

    it('uses existing output as base when reprocessing is enabled', function () use (&$output): void {
        // Arrange -- Pass 1: merge FIRST into the template, producing an output file
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_reprocess_');
        assert(is_string($tempFile));
        $output = $tempFile . '.docx';
        unlink($tempFile);
        $merger = new DocxMerger();

        $pass1 = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['FIRST' => fixture('source-a.docx')],
            outputPath: $output,
        );
        expect($pass1->success)->toBeTrue();

        // Verify pass 1 output: FIRST replaced, SECOND still present
        $zip1 = new ZipArchive();
        $zip1->open($output);
        $doc1 = $zip1->getFromName('word/document.xml');
        assert(is_string($doc1));
        expect($doc1)->not->toContain('${FIRST}');
        expect($doc1)->toContain('Content from source A');
        expect($doc1)->toContain('${SECOND}');
        $zip1->close();

        // Act -- Pass 2: reprocess the output, replacing SECOND
        $pass2 = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['SECOND' => fixture('source-b.docx')],
            outputPath: $output,
            options: new MergeOptions(isReprocessing: true),
        );

        // Assert -- Pass 2 should use the output from pass 1 as base
        expect($pass2->success)->toBeTrue();

        $zip2 = new ZipArchive();
        $zip2->open($output);
        $doc2 = $zip2->getFromName('word/document.xml');
        assert(is_string($doc2));

        // Both markers should be replaced
        expect($doc2)->not->toContain('${FIRST}');
        expect($doc2)->not->toContain('${SECOND}');
        // Content from both sources should be present
        expect($doc2)->toContain('Content from source A');
        expect($doc2)->toContain('Content from source B');
    });

    it('falls back to template when reprocessing is enabled but output does not exist', function () use (&$output): void {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_reprocess_');
        assert(is_string($tempFile));
        $output = $tempFile . '.docx';
        unlink($tempFile);
        // Ensure output does not exist
        if (file_exists($output)) {
            unlink($output);
        }
        $merger = new DocxMerger();

        // Act -- reprocessing enabled, but output does not exist yet
        $result = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['FIRST' => fixture('source-a.docx')],
            outputPath: $output,
            options: new MergeOptions(isReprocessing: true),
        );

        // Assert -- should fall back to template and work normally
        expect($result->success)->toBeTrue();
        expect(file_exists($output))->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($output);
        $docXml = $zip->getFromName('word/document.xml');
        assert(is_string($docXml));
        expect($docXml)->not->toContain('${FIRST}');
        expect($docXml)->toContain('Content from source A');
        $zip->close();
    });

    it('uses template as base when reprocessing is disabled', function () use (&$output): void {
        // Arrange -- Create an initial output
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_reprocess_');
        assert(is_string($tempFile));
        $output = $tempFile . '.docx';
        unlink($tempFile);
        $merger = new DocxMerger();

        $pass1 = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['FIRST' => fixture('source-a.docx')],
            outputPath: $output,
        );
        expect($pass1->success)->toBeTrue();

        // Act -- merge again WITHOUT reprocessing (default behavior)
        $pass2 = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['SECOND' => fixture('source-b.docx')],
            outputPath: $output,
            options: new MergeOptions(isReprocessing: false),
        );

        // Assert -- should use original template, NOT the previous output
        expect($pass2->success)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($output);
        $docXml = $zip->getFromName('word/document.xml');
        assert(is_string($docXml));

        // FIRST should still be present (not replaced, using fresh template)
        expect($docXml)->toContain('${FIRST}');
        // SECOND should be replaced
        expect($docXml)->not->toContain('${SECOND}');
        expect($docXml)->toContain('Content from source B');
        $zip->close();
    });

    it('merges using previous output as base in reprocessing mode', function () use (&$output): void {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_reprocess_');
        assert(is_string($tempFile));
        $output = $tempFile . '.docx';
        unlink($tempFile);
        $merger = new DocxMerger();

        // Pass 1: replace FIRST
        $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['FIRST' => fixture('source-reprocessing.docx')],
            outputPath: $output,
        );

        // Act -- Pass 2: reprocess, replace SECOND
        $result = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['SECOND' => fixture('source-b.docx')],
            outputPath: $output,
            options: new MergeOptions(isReprocessing: true),
        );

        // Assert
        expect($result->success)->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($output);
        $docXml = $zip->getFromName('word/document.xml');
        assert(is_string($docXml));

        // Both markers replaced
        expect($docXml)->not->toContain('${FIRST}');
        expect($docXml)->not->toContain('${SECOND}');

        // Content from both passes present
        expect($docXml)->toContain('Reprocessing source content');
        expect($docXml)->toContain('Content from source B');

        // Context text from template preserved
        expect($docXml)->toContain('Before first marker');
        expect($docXml)->toContain('Between markers');
        expect($docXml)->toContain('After second marker');

        $zip->close();
    });

    it('uses template when reprocessing but output does not exist', function () use (&$output): void {
        // Arrange
        $output = sys_get_temp_dir() . '/docx_nonexistent_' . uniqid() . '.docx';
        // Ensure output does NOT exist
        expect(file_exists($output))->toBeFalse();
        $merger = new DocxMerger();

        // Act
        $result = $merger->merge(
            templatePath: fixture('template-reprocessing.docx'),
            merges: ['FIRST' => fixture('source-reprocessing.docx')],
            outputPath: $output,
            options: new MergeOptions(isReprocessing: true),
        );

        // Assert
        expect($result->success)->toBeTrue();
        expect(file_exists($output))->toBeTrue();

        $zip = new ZipArchive();
        $zip->open($output);
        $docXml = $zip->getFromName('word/document.xml');
        assert(is_string($docXml));

        // FIRST replaced from template
        expect($docXml)->not->toContain('${FIRST}');
        expect($docXml)->toContain('Reprocessing source content');
        // SECOND still present (only FIRST was replaced)
        expect($docXml)->toContain('${SECOND}');

        $zip->close();
    });
});
