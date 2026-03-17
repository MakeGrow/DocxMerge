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
use DocxMerge\Merge\MergeOrchestrator;

describe('MergeOrchestrator', function (): void {
    describe('execute()', function (): void {
        it('returns a MergeResult after executing the pipeline', function (): void {
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

            // Cleanup
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        });
    });
});
