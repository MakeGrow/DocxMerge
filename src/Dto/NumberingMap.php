<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

use DOMElement;

/**
 * Maps source numbering IDs to target numbering IDs.
 *
 * Contains both abstractNumId and numId mappings, plus the DOM nodes
 * that need to be imported into the target numbering.xml.
 */
final class NumberingMap
{
    /**
     * @param array<int, int> $abstractNumMap Old abstractNumId => new abstractNumId.
     * @param array<int, int> $numMap Old numId => new numId.
     * @param list<DOMElement> $abstractNumNodes Abstract numbering nodes to import.
     * @param list<DOMElement> $numNodes Numbering instance nodes to import.
     */
    public function __construct(
        public readonly array $abstractNumMap,
        public readonly array $numMap,
        public readonly array $abstractNumNodes,
        public readonly array $numNodes,
    ) {
    }

    /**
     * Returns the new numId for a given old numId.
     *
     * @param int $oldNumId The source numId.
     *
     * @return int|null The mapped target numId, or null if not mapped.
     */
    public function getNewNumId(int $oldNumId): ?int
    {
        return $this->numMap[$oldNumId] ?? null;
    }

    /**
     * Returns the new abstractNumId for a given old abstractNumId.
     *
     * @param int $oldAbstractNumId The source abstractNumId.
     *
     * @return int|null The mapped target abstractNumId, or null if not mapped.
     */
    public function getNewAbstractNumId(int $oldAbstractNumId): ?int
    {
        return $this->abstractNumMap[$oldAbstractNumId] ?? null;
    }
}
