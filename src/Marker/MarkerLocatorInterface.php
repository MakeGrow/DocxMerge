<?php

declare(strict_types=1);

namespace DocxMerge\Marker;

use DocxMerge\Dto\MarkerLocation;
use DOMDocument;

/**
 * Contract for locating marker paragraphs in a template document.
 *
 * Implementations must handle markers split across multiple w:r/w:t elements
 * by reconstructing the full text of each paragraph.
 */
interface MarkerLocatorInterface
{
    /**
     * Finds the paragraph element containing the given marker.
     *
     * @param DOMDocument $documentDom The template document DOM.
     * @param string $markerName The marker name without delimiters (e.g., 'CONTENT').
     * @param string $markerPattern Regex pattern for markers.
     *
     * @return MarkerLocation|null The located marker, or null if not found.
     */
    public function locate(
        DOMDocument $documentDom,
        string $markerName,
        string $markerPattern,
    ): ?MarkerLocation;
}
