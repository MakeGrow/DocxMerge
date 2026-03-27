<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

use DOMElement;
use DOMNode;

/**
 * Represents content extracted from a source document body.
 *
 * Contains the DOM nodes to insert, the final section properties,
 * any intermediate section properties, and metadata about the source.
 *
 * @codeCoverageIgnore
 */
final class ExtractedContent
{
    /**
     * @param list<DOMNode> $nodes The extracted body nodes (paragraphs, tables, etc.).
     * @param DOMElement|null $finalSectPr The sectPr of the last section.
     * @param list<DOMElement> $intermediateSectPrElements SectPr elements embedded in content.
     * @param int $sectionCount Total sections in the source document.
     */
    public function __construct(
        public readonly array $nodes,
        public readonly ?DOMElement $finalSectPr,
        public readonly array $intermediateSectPrElements,
        public readonly int $sectionCount,
    ) {
    }
}
