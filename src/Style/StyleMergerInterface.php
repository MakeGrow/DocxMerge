<?php

declare(strict_types=1);

namespace DocxMerge\Style;

use DocxMerge\Dto\StyleMap;
use DOMDocument;

/**
 * Contract for merging styles from a source DOCX into a target DOCX.
 *
 * Implementations must detect identical styles by content hash comparison,
 * resolve ID conflicts by generating sequential numeric IDs, and update
 * basedOn/next/link references in imported styles.
 *
 * @codeCoverageIgnore
 */
interface StyleMergerInterface
{
    /**
     * Builds a mapping from source style IDs to target style IDs.
     *
     * Uses content hash comparison (O(1) lookup) to detect equivalent styles.
     * When a style ID conflict exists with different content, generates a new
     * sequential numeric ID starting from 1000.
     *
     * @param DOMDocument $sourceStylesDom The source styles.xml DOM.
     * @param DOMDocument $targetStylesDom The target styles.xml DOM.
     *
     * @return StyleMap Mapping of old IDs to new IDs with metadata.
     */
    public function buildMap(
        DOMDocument $sourceStylesDom,
        DOMDocument $targetStylesDom,
    ): StyleMap;

    /**
     * Merges mapped styles into the target DOM.
     *
     * Only imports styles that are new or renamed. Skips styles flagged as
     * reuse_existing. Updates basedOn, next, and link references.
     *
     * @param DOMDocument $targetStylesDom The target styles.xml DOM (modified in place).
     * @param StyleMap $styleMap The computed style map.
     *
     * @return int Number of styles actually imported.
     */
    public function merge(
        DOMDocument $targetStylesDom,
        StyleMap $styleMap,
    ): int;
}
