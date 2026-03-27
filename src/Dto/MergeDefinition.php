<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Defines a merge operation linking a marker to a source document section.
 *
 * When sectionIndex is null, the entire source document is used.
 * When specified, only that zero-based section is extracted.
 *
 * @codeCoverageIgnore
 */
final class MergeDefinition
{
    /**
     * @param string $markerName The marker name without delimiters (e.g., 'CONTENT').
     * @param string $sourcePath Absolute path to the source DOCX file.
     * @param int|null $sectionIndex Zero-based section index, or null for all.
     */
    public function __construct(
        public readonly string $markerName,
        public readonly string $sourcePath,
        public readonly ?int $sectionIndex = null,
    ) {
    }
}
