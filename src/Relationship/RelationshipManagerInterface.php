<?php

declare(strict_types=1);

namespace DocxMerge\Relationship;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;

/**
 * Contract for managing relationship mappings between source and target documents.
 *
 * Implementations must exclude header/footer relationships (handled by
 * HeaderFooterCopier) and structural relationships that should never be
 * duplicated, filtering to include only relationships actually referenced
 * in the content.
 */
interface RelationshipManagerInterface
{
    /**
     * Builds a mapping from source relationship IDs to new target IDs.
     *
     * Excludes header/footer relationships (handled by HeaderFooterCopier).
     * Excludes structural relationships that should never be duplicated.
     * Filters to include only relationships actually referenced in the content.
     *
     * @param DOMDocument $sourceRelsDom The source document.xml.rels DOM.
     * @param DOMDocument $targetRelsDom The target document.xml.rels DOM.
     * @param string $contentXml The extracted content XML (to filter referenced rIds).
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return RelationshipMap Mapping of old rIds to new rIds with metadata.
     */
    public function buildMap(
        DOMDocument $sourceRelsDom,
        DOMDocument $targetRelsDom,
        string $contentXml,
        IdTracker $idTracker,
    ): RelationshipMap;

    /**
     * Adds new relationships to the target rels DOM.
     *
     * Detects duplicates by Type+Target combination and reuses existing IDs.
     *
     * @param DOMDocument $targetRelsDom The target rels DOM (modified in place).
     * @param RelationshipMap $relationshipMap The computed relationship map.
     *
     * @return void
     */
    public function addRelationships(
        DOMDocument $targetRelsDom,
        RelationshipMap $relationshipMap,
    ): void;
}
