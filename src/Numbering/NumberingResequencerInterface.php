<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DOMDocument;

/**
 * Contract for resequencing numbering IDs after all merge passes are complete.
 *
 * Implementations must renumber all abstractNumId and numId values sequentially,
 * reorder DOM nodes so that all w:abstractNum precede all w:num, and update
 * all w:numId references in the document DOM.
 */
interface NumberingResequencerInterface
{
    /**
     * Renumbers all abstractNum and num IDs sequentially and enforces DOM ordering.
     *
     * abstractNumId: 0, 1, 2, ...
     * numId: 1, 2, 3, ...
     *
     * Also reorders DOM nodes so that all w:abstractNum precede all w:num.
     * Updates all w:numId references in the document DOM.
     *
     * @param DOMDocument $numberingDom The numbering.xml DOM (modified in place).
     * @param DOMDocument $documentDom The document.xml DOM (modified in place for numId refs).
     *
     * @return void
     */
    public function resequence(
        DOMDocument $numberingDom,
        DOMDocument $documentDom,
    ): void;
}
