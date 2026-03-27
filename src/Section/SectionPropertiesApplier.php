<?php

declare(strict_types=1);

namespace DocxMerge\Section;

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;

/**
 * Applies section properties from a source document to the target.
 *
 * Remaps headerReference and footerReference rIds in intermediate section
 * properties using the HeaderFooterMap, so that the imported content points
 * to the newly copied header/footer files in the target archive.
 */
final class SectionPropertiesApplier implements SectionPropertiesApplierInterface
{
    /**
     * Applies section properties from the source to the target document.
     *
     * Iterates over intermediate sectPr elements in the extracted content
     * and remaps headerReference/footerReference r:id attributes using the
     * provided HeaderFooterMap.
     *
     * @param DOMDocument $documentDom The target document DOM (modified in place).
     * @param DOMElement $markerParent The parent element where content was inserted.
     * @param ExtractedContent $extractedContent The extracted content with section properties.
     * @param HeaderFooterMap $headerFooterMap Map of old to new header/footer rIds.
     */
    public function apply(
        DOMDocument $documentDom,
        DOMElement $markerParent,
        ExtractedContent $extractedContent,
        HeaderFooterMap $headerFooterMap,
    ): void {
        if ($headerFooterMap->mappings === []) {
            return;
        }

        // Remap rIds in intermediate sectPr elements
        foreach ($extractedContent->intermediateSectPrElements as $sectPr) {
            $this->remapReferences($sectPr, $headerFooterMap);
        }
    }

    /**
     * Remaps r:id attributes on headerReference and footerReference elements.
     *
     * @param DOMElement $sectPr The sectPr element to process.
     * @param HeaderFooterMap $headerFooterMap Map of old to new rIds.
     */
    private function remapReferences(DOMElement $sectPr, HeaderFooterMap $headerFooterMap): void
    {
        // Process both headerReference and footerReference child elements
        for ($i = 0; $i < $sectPr->childNodes->length; $i++) {
            $child = $sectPr->childNodes->item($i);

            if (!$child instanceof DOMElement) {
                continue;
            }

            $localName = $child->localName;

            if ($localName !== 'headerReference' && $localName !== 'footerReference') {
                continue;
            }

            $oldRId = $child->getAttributeNS(XmlHelper::NS_R, 'id');

            if ($oldRId === '') {
                continue;
            }

            $newRId = $headerFooterMap->getNewRelId($oldRId);

            if ($newRId === null) {
                continue;
            }

            $child->setAttributeNS(XmlHelper::NS_R, 'r:id', $newRId);
        }
    }
}
