<?php

declare(strict_types=1);

namespace DocxMerge\Relationship;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;

/**
 * Manages relationship mappings between source and target documents.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see RelationshipManagerInterface
 */
final class RelationshipManager implements RelationshipManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildMap(
        DOMDocument $sourceRelsDom,
        DOMDocument $targetRelsDom,
        string $contentXml,
        IdTracker $idTracker,
    ): RelationshipMap {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }

    /**
     * {@inheritdoc}
     */
    public function addRelationships(
        DOMDocument $targetRelsDom,
        RelationshipMap $relationshipMap,
    ): void {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
