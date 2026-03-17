<?php

declare(strict_types=1);

namespace DocxMerge\Content;

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Exception\InvalidSourceException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Extracts body content from a source DOCX document.
 *
 * Handles single and multi-section documents by identifying intermediate
 * sectPr elements (nested in w:pPr) and the final sectPr (last child of
 * w:body). Supports full-document extraction and single-section extraction
 * by index.
 *
 * @see ContentExtractorInterface
 */
final class ContentExtractor implements ContentExtractorInterface
{
    /** WordprocessingML main namespace URI. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Extracts body content from a source document.
     *
     * When sectionIndex is null, extracts the entire body minus the final sectPr.
     * When sectionIndex is specified, extracts only the content of that section.
     * Intermediate sectPr elements are preserved as part of the extracted nodes.
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
    ): ExtractedContent {
        $xpath = $this->createXpath($sourceDom);
        $body = $this->findBody($xpath);

        // --- Phase 1: Identify final sectPr and collect all child nodes ---
        $finalSectPr = $this->findFinalSectPr($body);
        $allChildren = $this->collectBodyChildren($body, $finalSectPr);

        // --- Phase 2: Identify intermediate sectPr elements and section boundaries ---
        $intermediateSectPrElements = $this->findIntermediateSectPrElements($xpath, $allChildren);
        $sectionCount = count($intermediateSectPrElements) + 1;

        // --- Phase 3: Filter by section index if specified ---
        if ($sectionIndex !== null) {
            if ($sectionIndex < 0 || $sectionIndex >= $sectionCount) {
                throw new InvalidSourceException(
                    "Section index {$sectionIndex} is out of bounds. Document has {$sectionCount} section(s)."
                );
            }

            $allChildren = $this->extractSectionNodes($allChildren, $intermediateSectPrElements, $sectionIndex);
        }

        return new ExtractedContent(
            nodes: $allChildren,
            finalSectPr: $finalSectPr,
            intermediateSectPrElements: $intermediateSectPrElements,
            sectionCount: $sectionCount,
        );
    }

    /**
     * Counts the number of sections in a source document.
     *
     * Section count equals the number of intermediate sectPr elements plus one
     * (for the final sectPr which is always present in a valid document).
     *
     * @param DOMDocument $sourceDom The source document DOM.
     *
     * @return int Number of sections (always >= 1).
     */
    public function countSections(DOMDocument $sourceDom): int
    {
        $xpath = $this->createXpath($sourceDom);
        $body = $this->findBody($xpath);

        $allChildren = $this->collectBodyChildren($body, $this->findFinalSectPr($body));
        $intermediateSectPrElements = $this->findIntermediateSectPrElements($xpath, $allChildren);

        return count($intermediateSectPrElements) + 1;
    }

    /**
     * Creates a DOMXPath with the WordprocessingML namespace registered.
     *
     * @param DOMDocument $dom The document to create an XPath for.
     *
     * @return DOMXPath The configured XPath instance.
     */
    private function createXpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS_W);

        return $xpath;
    }

    /**
     * Finds the w:body element in the document.
     *
     * @param DOMXPath $xpath The configured XPath instance.
     *
     * @return DOMElement The w:body element.
     *
     * @throws InvalidSourceException If w:body is not found.
     */
    private function findBody(DOMXPath $xpath): DOMElement
    {
        $bodies = $xpath->query('//w:body');

        if ($bodies === false || $bodies->length === 0) {
            throw new InvalidSourceException('Source document does not contain a w:body element.');
        }

        $body = $bodies->item(0);
        assert($body instanceof DOMElement);

        return $body;
    }

    /**
     * Finds the final sectPr element (last child of w:body that is a sectPr).
     *
     * @param DOMElement $body The w:body element.
     *
     * @return DOMElement|null The final sectPr element, or null if not found.
     */
    private function findFinalSectPr(DOMElement $body): ?DOMElement
    {
        // The final sectPr is the last element child of w:body
        $lastChild = $body->lastChild;

        // Skip text nodes to find the last element
        while ($lastChild !== null && !($lastChild instanceof DOMElement)) {
            $lastChild = $lastChild->previousSibling;
        }

        if ($lastChild instanceof DOMElement && $lastChild->localName === 'sectPr') {
            return $lastChild;
        }

        return null;
    }

    /**
     * Collects all body child nodes except the final sectPr.
     *
     * @param DOMElement $body The w:body element.
     * @param DOMElement|null $finalSectPr The final sectPr to exclude.
     *
     * @return list<DOMNode> The body child nodes without the final sectPr.
     */
    private function collectBodyChildren(DOMElement $body, ?DOMElement $finalSectPr): array
    {
        $children = [];

        foreach ($body->childNodes as $child) {
            // Skip text/whitespace nodes
            if (!($child instanceof DOMElement)) {
                continue;
            }

            // Exclude the final sectPr
            if ($finalSectPr !== null && $child->isSameNode($finalSectPr)) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * Finds intermediate sectPr elements nested inside w:pPr of paragraphs.
     *
     * Intermediate section breaks are defined by a w:sectPr inside the w:pPr
     * of a paragraph, as opposed to the final sectPr which is a direct child
     * of w:body.
     *
     * @param DOMXPath $xpath The configured XPath instance.
     * @param list<DOMNode> $nodes The nodes to search within.
     *
     * @return list<DOMElement> The intermediate sectPr elements found.
     */
    private function findIntermediateSectPrElements(DOMXPath $xpath, array $nodes): array
    {
        $intermediate = [];

        foreach ($nodes as $node) {
            if (!($node instanceof DOMElement) || $node->localName !== 'p') {
                continue;
            }

            // Look for w:pPr/w:sectPr inside this paragraph
            $sectPrNodes = $xpath->query('w:pPr/w:sectPr', $node);
            if ($sectPrNodes !== false && $sectPrNodes->length > 0) {
                $sectPr = $sectPrNodes->item(0);
                assert($sectPr instanceof DOMElement);
                $intermediate[] = $sectPr;
            }
        }

        return $intermediate;
    }

    /**
     * Extracts nodes belonging to a specific section index.
     *
     * Sections are delimited by paragraphs containing intermediate sectPr
     * elements. The paragraph carrying the sectPr belongs to the section
     * it closes (i.e., it is the last node of that section).
     *
     * @param list<DOMNode> $allChildren All body children (excluding final sectPr).
     * @param list<DOMElement> $intermediateSectPrElements The intermediate sectPr elements.
     * @param int $sectionIndex The zero-based section index to extract.
     *
     * @return list<DOMNode> The nodes belonging to the requested section.
     */
    private function extractSectionNodes(
        array $allChildren,
        array $intermediateSectPrElements,
        int $sectionIndex,
    ): array {
        // Build list of paragraph nodes that carry intermediate sectPr
        $sectionBreakParagraphs = [];
        foreach ($intermediateSectPrElements as $sectPr) {
            // The sectPr is inside w:pPr, which is inside w:p
            $paragraph = $sectPr->parentNode?->parentNode;
            if ($paragraph instanceof DOMElement) {
                $sectionBreakParagraphs[] = $paragraph;
            }
        }

        // Split children into sections
        $currentSection = 0;
        $sectionNodes = [];

        foreach ($allChildren as $child) {
            if ($currentSection === $sectionIndex) {
                $sectionNodes[] = $child;
            }

            // Check if this child is a section-break paragraph
            foreach ($sectionBreakParagraphs as $breakParagraph) {
                if ($child instanceof DOMElement && $child->isSameNode($breakParagraph)) {
                    $currentSection++;
                    break;
                }
            }
        }

        return $sectionNodes;
    }
}
