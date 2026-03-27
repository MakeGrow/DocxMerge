<?php

declare(strict_types=1);

namespace DocxMerge\Section;

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Dto\HeaderFooterMap;
use DOMDocument;
use DOMElement;

/**
 * Contract for applying section properties from a source document to the target.
 *
 * Implementations must handle both the final sectPr (applied to the target
 * section where the marker was located) and intermediate sectPr elements
 * (embedded in imported content with headerReference/footerReference rIds
 * that need remapping).
 *
 * @codeCoverageIgnore
 */
interface SectionPropertiesApplierInterface
{
    /**
     * Applies section properties from the source to the target document.
     *
     * For the final section of the source, replaces or updates the sectPr
     * in the target section where the marker was located.
     *
     * For intermediate sections, the sectPr is already embedded in the
     * extracted content (inside w:pPr). This method updates the headerReference
     * and footerReference rIds in those embedded sectPr to point to the
     * newly copied header/footer files.
     *
     * @param DOMDocument $documentDom The target document DOM (modified in place).
     * @param DOMElement $markerParent The parent element where content was inserted.
     * @param ExtractedContent $extractedContent The extracted content with section properties.
     * @param HeaderFooterMap $headerFooterMap Map of old to new header/footer rIds.
     *
     * @return void
     */
    public function apply(
        DOMDocument $documentDom,
        DOMElement $markerParent,
        ExtractedContent $extractedContent,
        HeaderFooterMap $headerFooterMap,
    ): void;
}
