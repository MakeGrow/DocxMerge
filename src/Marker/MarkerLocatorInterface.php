<?php

declare(strict_types=1);

namespace DocxMerge\Marker;

use DocxMerge\Dto\MarkerLocation;
use DocxMerge\Exception\MergeException;
use DOMDocument;

/**
 * Contract for locating marker paragraphs in a template document.
 *
 * Implementations must handle markers split across multiple w:r/w:t elements
 * by reconstructing the full text of each paragraph.
 *
 * @codeCoverageIgnore
 */
interface MarkerLocatorInterface
{
    /**
     * Finds the paragraph element containing the given marker.
     *
     * Iterates all w:p elements, concatenates descendant w:t text, and matches
     * the marker pattern via regex. Supports markers fragmented across runs.
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
    ): ?MarkerLocation;
}
