<?php

declare(strict_types=1);

/**
 * Integration tests for DocxMerger.
 *
 * Exercises the full merge pipeline with real DOCX fixture files,
 * verifying output structure, content correctness, and error handling.
 */

use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeDefinition;
use DocxMerge\Dto\MergeOptions;
use DocxMerge\Dto\MergeResult;
use DocxMerge\Exception\InvalidTemplateException;
use DocxMerge\Exception\MarkerNotFoundException;

describe('DocxMerger', function (): void {
    // Holds the path of the output file created during the test so it can be
    // deleted in afterEach() even when the test fails.
    /** @var string $output */
    $output = '';

    afterEach(function () use (&$output): void {
        if ($output !== '' && file_exists($output)) {
            unlink($output);
        }
        $output = '';
    });

    describe('merge()', function () use (&$output): void {
        it('replaces a single marker with source content', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-simple.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result)->toBeInstanceOf(MergeResult::class);
            expect($result->success)->toBeTrue();
            expect(file_exists($output))->toBeTrue();

            // Verify output is a valid ZIP with document.xml
            $zip = new ZipArchive();
            expect($zip->open($output))->toBe(true);

            $docXml = $zip->getFromName('word/document.xml');
            expect($docXml)->not->toBeFalse();
            assert(is_string($docXml));

            // Marker should be removed
            expect($docXml)->not->toContain('${CONTENT}');
            // Source content should be present
            expect($docXml)->toContain('Hello from source');
            $zip->close();
        });

        it('replaces multiple markers in a single template', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-multi.docx'),
                merges: [
                    'FIRST' => fixture('source-a.docx'),
                    'SECOND' => fixture('source-b.docx'),
                ],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);
            $docXml = $zip->getFromName('word/document.xml');
            assert(is_string($docXml));
            expect($docXml)->not->toContain('${FIRST}');
            expect($docXml)->not->toContain('${SECOND}');
            $zip->close();
        });

        it('copies images from source to target', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-with-images.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);

            // At least one image should exist in word/media/
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
            $zip->close();
        });

        it('preserves numbering definitions from source', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-with-lists.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);
            $numbering = $zip->getFromName('word/numbering.xml');
            expect($numbering)->not->toBeFalse();
            $zip->close();
        });

        it('handles a fragmented marker split across multiple runs', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-fragmented.docx'),
                merges: ['CONTENT' => fixture('source-simple.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);
            $docXml = $zip->getFromName('word/document.xml');
            assert(is_string($docXml));
            expect($docXml)->not->toContain('${CONTENT}');
            $zip->close();
        });

        it('throws MarkerNotFoundException in strict mode', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act + Assert
            expect(fn () => $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['NONEXISTENT' => fixture('source-simple.docx')],
                outputPath: $output,
                options: new MergeOptions(strictMarkers: true),
            ))->toThrow(MarkerNotFoundException::class);
        });

        it('emits warning for missing marker in non-strict mode', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['NONEXISTENT' => fixture('source-simple.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();
            expect($result->warnings)->not->toBeEmpty();
        });

        it('throws InvalidTemplateException for non-existent template', function (): void {
            // Arrange
            $merger = new DocxMerger();

            // Act + Assert
            expect(fn () => $merger->merge(
                templatePath: '/non/existent/template.docx',
                merges: ['CONTENT' => fixture('source-simple.docx')],
                outputPath: '/tmp/output.docx',
            ))->toThrow(InvalidTemplateException::class);
        });

        it('registers numbering.xml content type and relationship when template has no numbering', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act -- template-simple.docx does NOT have numbering.xml
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-with-lists.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);

            // numbering.xml must exist
            $numbering = $zip->getFromName('word/numbering.xml');
            expect($numbering)->not->toBeFalse();

            // [Content_Types].xml must have Override for numbering.xml
            $contentTypes = $zip->getFromName('[Content_Types].xml');
            assert(is_string($contentTypes));
            expect($contentTypes)->toContain('PartName="/word/numbering.xml"');
            expect($contentTypes)->toContain('numbering+xml');

            // document.xml.rels must have Relationship for numbering
            $rels = $zip->getFromName('word/_rels/document.xml.rels');
            assert(is_string($rels));
            expect($rels)->toContain('relationships/numbering');
            expect($rels)->toContain('numbering.xml');

            $zip->close();
        });

        it('correctly maps media files with non-standard names', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-with-photo.docx')],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();

            $zip = new ZipArchive();
            $zip->open($output);

            // An image file must exist in word/media/ with a sequential name
            $hasImage = false;
            $imageName = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                /** @var string $name */
                $name = $zip->getNameIndex($i);
                if (preg_match('#^word/media/image\d+\.png$#', $name)) {
                    $hasImage = true;
                    $imageName = str_replace('word/', '', $name);
                    break;
                }
            }
            expect($hasImage)->toBeTrue();

            // The relationship must point to the renamed file, not "media/photo.png"
            $rels = $zip->getFromName('word/_rels/document.xml.rels');
            assert(is_string($rels));
            expect($rels)->not->toContain('photo.png');
            expect($rels)->toContain($imageName);

            $zip->close();
        });

        it('propagates logger to the orchestrator', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';

            /** @var list<array{level: string, message: string}> $logs */
            $logs = [];
            $logger = new class ($logs) extends \Psr\Log\AbstractLogger {
                /** @param list<array{level: string, message: string}> $logs */
                public function __construct(
                    /** @phpstan-ignore property.onlyWritten (read via by-reference binding in outer scope) */
                    private array &$logs,
                ) {
                }

                public function log($level, string|\Stringable $message, array $context = []): void
                {
                    $this->logs[] = ['level' => (string) $level, 'message' => (string) $message];
                }
            };

            $merger = new DocxMerger(logger: $logger);

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-simple.docx'),
                merges: ['CONTENT' => fixture('source-simple.docx')],
                outputPath: $output,
            );

            // Assert -- the facade itself logs "Starting merge operation"
            expect($result->success)->toBeTrue();
            $debugMessages = array_filter($logs, fn ($log) => $log['level'] === 'debug');
            expect($debugMessages)->not->toBeEmpty();
        });

        it('handles source with multiple sections via MergeDefinition', function () use (&$output): void {
            // Arrange
            $output = tempnam(sys_get_temp_dir(), 'docx_test_') . '.docx';
            $merger = new DocxMerger();

            // Act
            $result = $merger->merge(
                templatePath: fixture('template-multi.docx'),
                merges: [
                    'FIRST' => new MergeDefinition(
                        markerName: 'FIRST',
                        sourcePath: fixture('source-multi-section.docx'),
                        sectionIndex: 0,
                    ),
                    'SECOND' => new MergeDefinition(
                        markerName: 'SECOND',
                        sourcePath: fixture('source-multi-section.docx'),
                        sectionIndex: 1,
                    ),
                ],
                outputPath: $output,
            );

            // Assert
            expect($result->success)->toBeTrue();
        });
    });
});
