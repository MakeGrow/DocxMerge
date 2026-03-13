<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Structured result of a merge operation.
 *
 * Contains success status, output path, accumulated errors and warnings,
 * processing statistics, and execution time.
 *
 * @phpstan-type Stats array<string, int>
 */
final class MergeResult
{
    /**
     * @param bool $success Whether the merge completed without fatal errors.
     * @param string $outputPath Absolute path to the generated output file.
     * @param list<string> $errors Non-fatal errors accumulated during merge.
     * @param list<string> $warnings Informational warnings.
     * @param array<string, int> $stats Processing counters (markers_replaced, images_copied, etc.).
     * @param float $executionTime Execution time in seconds.
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $outputPath,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly array $stats,
        public readonly float $executionTime,
    ) {
    }
}
