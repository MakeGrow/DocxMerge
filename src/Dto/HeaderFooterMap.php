<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Maps source header/footer relationship IDs to target IDs and filenames.
 *
 * Used by SectionPropertiesApplier to update headerReference and
 * footerReference rIds in intermediate and final section properties.
 */
final class HeaderFooterMap
{
    /**
     * @param array<string, HeaderFooterMapping> $mappings Key = old rId.
     */
    public function __construct(
        public readonly array $mappings,
    ) {
    }

    /**
     * Returns the new relationship ID for a given old rId.
     *
     * @param string $oldRId The source relationship ID.
     *
     * @return string|null The new relationship ID, or null if not mapped.
     */
    public function getNewRelId(string $oldRId): ?string
    {
        return isset($this->mappings[$oldRId])
            ? $this->mappings[$oldRId]->newRelId
            : null;
    }

    /**
     * Returns the new filename for a given old rId.
     *
     * @param string $oldRId The source relationship ID.
     *
     * @return string|null The new filename, or null if not mapped.
     */
    public function getNewFilename(string $oldRId): ?string
    {
        return isset($this->mappings[$oldRId])
            ? $this->mappings[$oldRId]->newFilename
            : null;
    }
}
