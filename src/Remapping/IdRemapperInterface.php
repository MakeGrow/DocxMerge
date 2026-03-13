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
 * Contract for remapping all IDs in extracted content before insertion.
 *
 * Implementations must process relationship IDs, style IDs, numbering IDs,
 * drawing object property IDs, and bookmark IDs to prevent collisions
 * with existing IDs in the target document.
 */
interface IdRemapperInterface
{
    /**
     * Remaps all IDs in the extracted content DOM nodes.
     *
     * Processes in order:
     * 1. r:embed and r:id attributes (relationship IDs)
     * 2. w:pStyle, w:rStyle, w:tblStyle attributes (style IDs)
     * 3. w:numId values inside w:numPr (numbering IDs, two-pass with temp offset)
     * 4. wp:docPr id attributes (drawing object IDs)
     * 5. w:bookmarkStart and w:bookmarkEnd w:id attributes (bookmark IDs)
     *
     * @param list<DOMNode> $contentNodes The content nodes to remap (modified in place).
     * @param RelationshipMap $relationshipMap rId mapping.
     * @param StyleMap $styleMap Style ID mapping.
     * @param NumberingMap $numberingMap Numbering ID mapping.
     * @param IdTracker $idTracker Shared ID counters for docPr and bookmarks.
     * @param DOMDocument $targetDom The target document DOM (for scanning existing docPr/bookmark IDs).
     *
     * @return void
     */
    public function remap(
        array $contentNodes,
        RelationshipMap $relationshipMap,
        StyleMap $styleMap,
        NumberingMap $numberingMap,
        IdTracker $idTracker,
        DOMDocument $targetDom,
    ): void;
}
