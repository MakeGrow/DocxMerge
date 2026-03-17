<?php

declare(strict_types=1);

namespace DocxMerge\Style;

use DocxMerge\Dto\StyleMap;
use DOMDocument;

/**
 * Merges styles from a source DOCX into a target DOCX.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see StyleMergerInterface
 */
final class StyleMerger implements StyleMergerInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildMap(
        DOMDocument $sourceStylesDom,
        DOMDocument $targetStylesDom,
    ): StyleMap {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }

    /**
     * {@inheritdoc}
     */
    public function merge(
        DOMDocument $targetStylesDom,
        StyleMap $styleMap,
    ): int {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
