<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Represents a single header/footer mapping from source to target.
 *
 * Tracks the old and new relationship IDs, the old target filename,
 * the new filename in the target archive, and the type of header/footer.
 */
final class HeaderFooterMapping
{
    /**
     * @param string $oldId The original relationship ID in the source.
     * @param string $newRelId The new relationship ID in the target.
     * @param string $oldTarget The original filename (e.g., "header1.xml").
     * @param string $newFilename The new filename in the target (e.g., "header3.xml").
     * @param string $type The header/footer type ("default", "first", "even").
     * @param bool $isHeader True for headers, false for footers.
     */
    public function __construct(
        public readonly string $oldId,
        public readonly string $newRelId,
        public readonly string $oldTarget,
        public readonly string $newFilename,
        public readonly string $type,
        public readonly bool $isHeader,
    ) {
    }
}
