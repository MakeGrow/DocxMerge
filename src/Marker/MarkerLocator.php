<?php

declare(strict_types=1);

namespace DocxMerge\Marker;

use DocxMerge\Dto\MarkerLocation;
use DocxMerge\Exception\MergeException;
use DocxMerge\Xml\XmlHelper;
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
    /**
     * Finds the paragraph element containing the given marker.
     *
     * Iterates all w:p elements in the document, concatenates the text content
     * of their descendant w:t elements, and matches the marker pattern via regex.
     * Supports markers fragmented across multiple runs by Word's serializer.
     *
     * @param DOMDocument $documentDom The template document DOM.
     * @param string $markerName The marker name without delimiters (e.g., 'CONTENT').
     * @param string $markerPattern PCRE regex pattern with at least one capture group
     *                              for the marker name (e.g., '/\$\{([A-Z_][A-Z0-9_]*)\}/').
     *
     * @return MarkerLocation|null The located marker, or null if not found.
     *
     * @throws MergeException If the marker pattern is not a valid PCRE regex or lacks
     *                        a capturing group (index 1) for the marker name.
     */
    public function locate(
        DOMDocument $documentDom,
        string $markerName,
        string $markerPattern,
    ): ?MarkerLocation {
        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('w', XmlHelper::NS_W);

        $paragraphs = $xpath->query('//w:p');

        // @codeCoverageIgnoreStart
        if ($paragraphs === false) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        // Validate syntax eagerly to provide a clear error before iterating paragraphs.
        // Suppress the E_WARNING that preg_match emits for invalid patterns so consumers
        // only see the MergeException, not a raw PHP warning.
        set_error_handler(static fn (): bool => true);
        $validationResult = preg_match($markerPattern, '');
        restore_error_handler();

        if ($validationResult === false) {
            $errorCode = preg_last_error();
            throw new MergeException(
                "Invalid marker pattern '{$markerPattern}': PCRE error code {$errorCode}."
            );
        }

        foreach ($paragraphs as $paragraph) {
            // @codeCoverageIgnoreStart
            if (!$paragraph instanceof DOMElement) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            // Collect all w:t descendants and concatenate their text to handle
            // markers split across multiple w:r/w:t elements by Word.
            $textNodes = $xpath->query('.//w:t', $paragraph);

            // @codeCoverageIgnoreStart
            if ($textNodes === false) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $fullText = '';
            /** @var list<DOMElement> $textElements */
            $textElements = [];

            foreach ($textNodes as $textNode) {
                // @codeCoverageIgnoreStart
                if (!$textNode instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd

                $fullText .= $textNode->nodeValue ?? '';
                $textElements[] = $textNode;
            }

            // Match the marker pattern against concatenated text to support any delimiter style.
            $matchCount = preg_match_all($markerPattern, $fullText, $matches);

            if ($matchCount === false) {
                $errorCode = preg_last_error();
                throw new MergeException(
                    "Marker pattern '{$markerPattern}' failed during matching: PCRE error code {$errorCode}."
                );
            }

            if ($matchCount > 0) {
                if (!isset($matches[1]) || $matches[1] === []) {
                    throw new MergeException(
                        "Marker pattern '{$markerPattern}' must define a capturing group (index 1) for the marker name."
                    );
                }
                foreach ($matches[1] as $capturedName) {
                    if ($capturedName === $markerName) {
                        return new MarkerLocation(
                            paragraph: $paragraph,
                            textNodes: $textElements,
                        );
                    }
                }
            }
        }

        return null;
    }
}
