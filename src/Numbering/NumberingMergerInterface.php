<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;

/**
 * Contract for merging numbering definitions from a source DOCX into a target DOCX.
 *
 * Implementations must filter to include only numbering definitions actually
 * referenced in the extracted content and maintain OOXML ordering requirements
 * (all w:abstractNum before all w:num).
 */
interface NumberingMergerInterface
{
    /**
     * Builds a mapping from source numbering IDs to target IDs.
     *
     * Filters to include only numbering definitions actually referenced
     * in the extracted content.
     *
     * @param DOMDocument $sourceNumberingDom The source numbering.xml DOM.
     * @param DOMDocument $targetNumberingDom The target numbering.xml DOM.
     * @param string $contentXml The extracted content XML (to filter used numIds).
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return NumberingMap Mapping of old IDs to new IDs.
     */
    public function buildMap(
        DOMDocument $sourceNumberingDom,
        DOMDocument $targetNumberingDom,
        string $contentXml,
        IdTracker $idTracker,
    ): NumberingMap;

    /**
     * Merges mapped numbering definitions into the target DOM.
     *
     * Inserts w:abstractNum elements before the first w:num element to maintain
     * OOXML ordering requirements. Inserts w:num elements after all w:abstractNum.
     *
     * @param DOMDocument $targetNumberingDom The target numbering.xml DOM (modified in place).
     * @param NumberingMap $numberingMap The computed numbering map.
     *
     * @return int Number of definitions imported.
     */
    public function merge(
        DOMDocument $targetNumberingDom,
        NumberingMap $numberingMap,
    ): int;
}
