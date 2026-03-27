<?php

declare(strict_types=1);

namespace DocxMerge\Content;

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Exception\InvalidSourceException;
use DOMDocument;

/**
 * Contract for extracting body content from a source DOCX document.
 *
 * Implementations must support full-document extraction and single-section
 * extraction, preserving intermediate sectPr elements properly encapsulated
 * in w:pPr.
 *
 * @codeCoverageIgnore
 */
interface ContentExtractorInterface
{
    /**
     * Extracts body content from a source document.
     *
     * When sectionIndex is null, extracts the entire body minus the final sectPr.
     * When sectionIndex is specified, extracts only the content of that section.
     * Intermediate sectPr elements are preserved and properly encapsulated in w:pPr.
     *
     * @param DOMDocument $sourceDom The source document DOM.
     * @param int|null $sectionIndex Zero-based section index, or null for all.
     *
     * @return ExtractedContent The extracted content with its nodes, section properties, and metadata.
     *
     * @throws InvalidSourceException If sectionIndex exceeds available sections.
     */
    public function extract(
        DOMDocument $sourceDom,
        ?int $sectionIndex = null,
    ): ExtractedContent;

    /**
     * Counts the number of sections in a source document.
     *
     * @param DOMDocument $sourceDom The source document DOM.
     *
     * @return int Number of sections (always >= 1).
     */
    public function countSections(DOMDocument $sourceDom): int;
}
