<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

use DOMElement;

/**
 * Represents a single style mapping from source to target.
 *
 * When reuseExisting is true, the style already exists identically in the
 * target and does not need to be imported.
 *
 * @codeCoverageIgnore
 */
final class StyleMapping
{
    /**
     * @param string $oldId The original style ID in the source document.
     * @param string $newId The mapped style ID in the target document.
     * @param string $type The style type (paragraph, character, table, numbering).
     * @param DOMElement $node The source w:style element.
     * @param bool $reuseExisting Whether the target already has an identical style.
     */
    public function __construct(
        public readonly string $oldId,
        public readonly string $newId,
        public readonly string $type,
        public readonly DOMElement $node,
        public readonly bool $reuseExisting,
    ) {
    }
}
