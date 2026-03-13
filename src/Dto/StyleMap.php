<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Maps source style IDs to target style IDs with conflict resolution metadata.
 *
 * Provides lookup methods for remapping style references in imported content
 * and filtering styles that need to be imported into the target.
 */
final class StyleMap
{
    /**
     * @param array<string, StyleMapping> $mappings Key = old style ID.
     */
    public function __construct(
        public readonly array $mappings,
    ) {
    }

    /**
     * Returns the new (target) ID for a given old (source) style ID.
     *
     * @param string $oldId The source style ID.
     *
     * @return string The mapped target style ID, or the original ID if not mapped.
     */
    public function getNewId(string $oldId): string
    {
        if (isset($this->mappings[$oldId])) {
            return $this->mappings[$oldId]->newId;
        }

        return $oldId;
    }

    /**
     * Checks whether a mapping exists for the given source style ID.
     *
     * @param string $oldId The source style ID.
     *
     * @return bool True if a mapping exists.
     */
    public function hasMapping(string $oldId): bool
    {
        return isset($this->mappings[$oldId]);
    }

    /**
     * Checks whether the given source style is reused from the target.
     *
     * @param string $oldId The source style ID.
     *
     * @return bool True if the style exists identically in the target.
     */
    public function isReused(string $oldId): bool
    {
        return isset($this->mappings[$oldId]) && $this->mappings[$oldId]->reuseExisting;
    }

    /**
     * Returns only the styles that need to be imported (not reused).
     *
     * @return array<string, StyleMapping> Filtered mappings.
     */
    public function getStylesToImport(): array
    {
        return array_filter(
            $this->mappings,
            static fn (StyleMapping $mapping): bool => !$mapping->reuseExisting,
        );
    }
}
