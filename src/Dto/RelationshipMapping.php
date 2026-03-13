<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Represents a single relationship mapping from source to target.
 *
 * Tracks the old and new relationship IDs, targets, and whether
 * the associated file needs to be physically copied to the target ZIP.
 */
final class RelationshipMapping
{
    /**
     * @param string $oldId The original relationship ID (e.g., "rId1").
     * @param string $newId The mapped relationship ID in the target.
     * @param string $type The relationship type URI.
     * @param string $target The original target path or URL.
     * @param string $newTarget The new target path in the target archive.
     * @param bool $needsFileCopy Whether the target file must be copied to the ZIP.
     * @param bool $isExternal Whether this is an external relationship (e.g., hyperlink).
     */
    public function __construct(
        public readonly string $oldId,
        public readonly string $newId,
        public readonly string $type,
        public readonly string $target,
        public readonly string $newTarget,
        public readonly bool $needsFileCopy,
        public readonly bool $isExternal,
    ) {
    }
}
