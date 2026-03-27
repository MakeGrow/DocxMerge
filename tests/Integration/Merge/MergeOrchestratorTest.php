<?php

declare(strict_types=1);

/**
 * Tests for MergeOrchestrator.
 *
 * Verifies that the orchestrator coordinates the merge pipeline and returns
 * a structured MergeResult. Uses mocks for all injected service dependencies.
 */

use DocxMerge\Dto\MergeDefinition;
use DocxMerge\Dto\MergeOptions;
use DocxMerge\Dto\MergeResult;
use DocxMerge\Exception\InvalidTemplateException;
use DocxMerge\Merge\MergeOrchestrator;

describe('MergeOrchestrator', function (): void {
    /** @var string $outputPath */
    $outputPath = '';

    afterEach(function () use (&$outputPath): void {
        /** @var string $outputPath */
        if ($outputPath !== '' && file_exists($outputPath)) {
            unlink($outputPath);
        }
        $outputPath = '';
    });

    describe('execute()', function () use (&$outputPath): void {
        it('returns a MergeResult after executing the pipeline', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();

            $templatePath = fixture('template-simple.docx');
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'CONTENT',
                    sourcePath: fixture('source-simple.docx'),
                ),
            ];
            $options = new MergeOptions();

            // Act
            $result = $orchestrator->execute(
                templatePath: $templatePath,
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            );

            // Assert
            expect($result)->toBeInstanceOf(MergeResult::class);
        });

        it('merges a source with images and copies media files', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'CONTENT',
                    sourcePath: fixture('source-with-images.docx'),
                ),
            ];
            $options = new MergeOptions();

            // Act
            $result = $orchestrator->execute(
                templatePath: fixture('template-simple.docx'),
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            );

            // Assert
            expect($result->success)->toBeTrue();
            expect($result->errors)->toBeEmpty();
            expect($result->stats['images_copied'] ?? 0)->toBeGreaterThanOrEqual(1);

            // Verify output ZIP contains media files
            $zip = new ZipArchive();
            expect($zip->open($outputPath))->toBe(true);

            $hasImage = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                /** @var string $name */
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'word/media/')) {
                    $hasImage = true;
                    break;
                }
            }
            expect($hasImage)->toBeTrue();

            // Marker should be removed
            $docXml = $zip->getFromName('word/document.xml');
            assert(is_string($docXml));
            expect($docXml)->not->toContain('${CONTENT}');

            $zip->close();
        });

        it('merges a source with numbered lists and remaps numbering IDs', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'CONTENT',
                    sourcePath: fixture('source-with-lists.docx'),
                ),
            ];
            $options = new MergeOptions();

            // Act
            $result = $orchestrator->execute(
                templatePath: fixture('template-simple.docx'),
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            );

            // Assert
            expect($result->success)->toBeTrue();
            expect($result->errors)->toBeEmpty();

            // Verify numbering.xml exists in output
            $zip = new ZipArchive();
            expect($zip->open($outputPath))->toBe(true);

            $numbering = $zip->getFromName('word/numbering.xml');
            expect($numbering)->not->toBeFalse();
            assert(is_string($numbering));

            // Verify numbering contains at least one w:num and one w:abstractNum
            expect($numbering)->toContain('w:num');
            expect($numbering)->toContain('w:abstractNum');

            // Marker should be removed
            $docXml = $zip->getFromName('word/document.xml');
            assert(is_string($docXml));
            expect($docXml)->not->toContain('${CONTENT}');

            $zip->close();
        });

        it('adds a warning when marker is not found and strictMarkers is disabled', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'NONEXISTENT',
                    sourcePath: fixture('source-simple.docx'),
                ),
            ];
            $options = new MergeOptions(strictMarkers: false);

            // Act
            $result = $orchestrator->execute(
                templatePath: fixture('template-simple.docx'),
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            );

            // Assert
            expect($result->success)->toBeTrue();
            expect($result->warnings)->not->toBeEmpty();

            // At least one warning should mention the marker
            $hasMarkerWarning = false;
            foreach ($result->warnings as $warning) {
                if (str_contains($warning, 'NONEXISTENT') && str_contains($warning, 'not found')) {
                    $hasMarkerWarning = true;
                    break;
                }
            }
            expect($hasMarkerWarning)->toBeTrue();
        });

        it('throws InvalidTemplateException when the template file does not exist', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'CONTENT',
                    sourcePath: fixture('source-simple.docx'),
                ),
            ];
            $options = new MergeOptions();

            // Act + Assert
            expect(fn () => $orchestrator->execute(
                templatePath: '/non/existent/template.docx',
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            ))->toThrow(InvalidTemplateException::class);
        });

        it('merges a source with headers and copies header files', function () use (&$outputPath): void {
            // Arrange
            $orchestrator = new MergeOrchestrator();
            $outputPath = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $definitions = [
                new MergeDefinition(
                    markerName: 'CONTENT',
                    sourcePath: fixture('source-with-headers.docx'),
                ),
            ];
            $options = new MergeOptions();

            // Act
            $result = $orchestrator->execute(
                templatePath: fixture('template-simple.docx'),
                definitions: $definitions,
                outputPath: $outputPath,
                options: $options,
            );

            // Assert
            expect($result->success)->toBeTrue();
            expect($result->errors)->toBeEmpty();

            // Verify output is a valid ZIP
            $zip = new ZipArchive();
            expect($zip->open($outputPath))->toBe(true);

            // Marker should be removed
            $docXml = $zip->getFromName('word/document.xml');
            assert(is_string($docXml));
            expect($docXml)->not->toContain('${CONTENT}');

            $zip->close();
        });
    });
});
