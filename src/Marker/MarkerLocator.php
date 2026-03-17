<?php

declare(strict_types=1);

namespace DocxMerge\Marker;

use DocxMerge\Dto\MarkerLocation;
use DOMDocument;

/**
 * Locates marker paragraphs in a template document.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see MarkerLocatorInterface
 */
final class MarkerLocator implements MarkerLocatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function locate(
        DOMDocument $documentDom,
        string $markerName,
        string $markerPattern,
    ): ?MarkerLocation {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
