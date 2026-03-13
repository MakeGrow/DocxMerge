<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Maps source relationship IDs to target relationship IDs.
 *
 * Provides lookup methods for remapping rId references in imported
 * content and filtering relationships that require file copy operations.
 */
final class RelationshipMap
{
    /**
     * @param array<string, RelationshipMapping> $mappings Key = old rId.
     */
    public function __construct(
        public readonly array $mappings,
    ) {
    }

    /**
     * Returns the new rId for a given old rId.
     *
     * @param string $oldRId The source relationship ID.
     *
     * @return string|null The mapped target rId, or null if not mapped.
     */
    public function getNewId(string $oldRId): ?string
    {
        return isset($this->mappings[$oldRId])
            ? $this->mappings[$oldRId]->newId
            : null;
    }

    /**
     * Returns the file target for a given old rId.
     *
     * @param string $oldRId The source relationship ID.
     *
     * @return string|null The target path, or null if not mapped.
     */
    public function getFileTarget(string $oldRId): ?string
    {
        return isset($this->mappings[$oldRId])
            ? $this->mappings[$oldRId]->target
            : null;
    }

    /**
     * Returns only the mappings that require file copy operations.
     *
     * @return array<string, RelationshipMapping> Filtered mappings.
     */
    public function getFilesToCopy(): array
    {
        return array_filter(
            $this->mappings,
            static fn (RelationshipMapping $mapping): bool => $mapping->needsFileCopy,
        );
    }
}
