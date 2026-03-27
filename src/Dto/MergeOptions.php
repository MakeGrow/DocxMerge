<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Configuration options for a merge operation.
 *
 * Controls marker pattern matching, strict mode, and reprocessing behavior.
 *
 * @codeCoverageIgnore
 */
final class MergeOptions
{
    /**
     * @param string $markerPattern Regex pattern for matching markers in the template.
     * @param bool $strictMarkers When true, throws if a marker is not found in the template.
     * @param bool $isReprocessing When true, uses existing output as the base for new merges.
     */
    public function __construct(
        public readonly string $markerPattern = '/\$\{([A-Z_][A-Z0-9_]*)\}/',
        public readonly bool $strictMarkers = false,
        public readonly bool $isReprocessing = false,
    ) {
    }
}
