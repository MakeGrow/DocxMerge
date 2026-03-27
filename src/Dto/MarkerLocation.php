<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

use DOMElement;

/**
 * Represents a located marker within a template document.
 *
 * Contains the paragraph element holding the marker and the specific
 * w:t elements that compose the marker text (which may span multiple runs).
 *
 * @codeCoverageIgnore
 */
final class MarkerLocation
{
    /**
     * @param DOMElement $paragraph The w:p element containing the marker.
     * @param list<DOMElement> $textNodes The w:t elements composing the marker text.
     */
    public function __construct(
        public readonly DOMElement $paragraph,
        public readonly array $textNodes,
    ) {
    }
}
