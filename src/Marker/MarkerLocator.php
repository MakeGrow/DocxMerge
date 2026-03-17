<?php

declare(strict_types=1);

namespace DocxMerge\Marker;

use DocxMerge\Dto\MarkerLocation;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Locates marker paragraphs in a template document.
 *
 * Handles markers split across multiple w:r/w:t elements by reconstructing
 * the full text of each paragraph and matching against the marker pattern.
 *
 * @see MarkerLocatorInterface
 */
final class MarkerLocator implements MarkerLocatorInterface
{
    /** WordprocessingML main namespace URI. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Finds the paragraph element containing the given marker.
     *
     * Iterates all w:p elements in the document, concatenates the text content
     * of their descendant w:t elements, and checks for the marker string.
     * Supports markers fragmented across multiple runs by Word's serializer.
     *
     * @param DOMDocument $documentDom The template document DOM.
     * @param string $markerName The marker name without delimiters (e.g., 'CONTENT').
     * @param string $markerPattern Regex pattern for markers (unused for specific lookup).
     *
     * @return MarkerLocation|null The located marker, or null if not found.
     */
    public function locate(
        DOMDocument $documentDom,
        string $markerName,
        string $markerPattern,
    ): ?MarkerLocation {
        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('w', self::NS_W);

        $paragraphs = $xpath->query('//w:p');

        if ($paragraphs === false) {
            return null;
        }

        // The full marker string to search for within concatenated paragraph text.
        $markerString = '${' . $markerName . '}';

        foreach ($paragraphs as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }

            // Collect all w:t descendants and concatenate their text to handle
            // markers split across multiple w:r/w:t elements by Word.
            $textNodes = $xpath->query('.//w:t', $paragraph);

            if ($textNodes === false) {
                continue;
            }

            $fullText = '';
            /** @var list<DOMElement> $textElements */
            $textElements = [];

            foreach ($textNodes as $textNode) {
                if (!$textNode instanceof DOMElement) {
                    continue;
                }

                $fullText .= $textNode->nodeValue ?? '';
                $textElements[] = $textNode;
            }

            if (str_contains($fullText, $markerString)) {
                return new MarkerLocation(
                    paragraph: $paragraph,
                    textNodes: $textElements,
                );
            }
        }

        return null;
    }
}
