<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;

/**
 * Merges numbering definitions from a source DOCX into a target DOCX.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see NumberingMergerInterface
 */
final class NumberingMerger implements NumberingMergerInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildMap(
        DOMDocument $sourceNumberingDom,
        DOMDocument $targetNumberingDom,
        string $contentXml,
        IdTracker $idTracker,
    ): NumberingMap {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }

    /**
     * {@inheritdoc}
     */
    public function merge(
        DOMDocument $targetNumberingDom,
        NumberingMap $numberingMap,
    ): int {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
