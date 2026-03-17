<?php

declare(strict_types=1);

namespace DocxMerge\Remapping;

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\StyleMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use DOMNode;

/**
 * Remaps all IDs in extracted content before insertion into the target.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see IdRemapperInterface
 */
final class IdRemapper implements IdRemapperInterface
{
    /**
     * {@inheritdoc}
     *
     * @param list<DOMNode> $contentNodes
     */
    public function remap(
        array $contentNodes,
        RelationshipMap $relationshipMap,
        StyleMap $styleMap,
        NumberingMap $numberingMap,
        IdTracker $idTracker,
        DOMDocument $targetDom,
    ): void {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
