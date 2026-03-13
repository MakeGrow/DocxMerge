<?php

declare(strict_types=1);

namespace DocxMerge\Tracking;

use DOMDocument;
use ZipArchive;

/**
 * Tracks ID counters across a merge operation to prevent collisions.
 *
 * Initialized from the target document state and incremented as new
 * IDs are assigned during merge processing.
 */
final class IdTracker
{
    private int $relationshipIdCounter = 0;
    private int $imageCounter = 0;
    private int $headerFooterCounter = 0;
    private int $styleIdCounter = 1000;
    private int $numIdCounter = 0;
    private int $abstractNumIdCounter = 0;
    private int $docPrIdCounter = 0;
    private int $bookmarkIdCounter = 0;

    /**
     * Initializes all counters by scanning the target ZIP and DOMs.
     *
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param DOMDocument $relsDom The target document.xml.rels DOM.
     * @param DOMDocument $documentDom The target document.xml DOM.
     * @param DOMDocument|null $numberingDom The target numbering.xml DOM.
     *
     * @return self Initialized tracker.
     */
    public static function initializeFromTarget(
        ZipArchive $targetZip,
        DOMDocument $relsDom,
        DOMDocument $documentDom,
        ?DOMDocument $numberingDom,
    ): self {
        // Stub -- real implementation in Phase 3
        return new self();
    }

    /**
     * Returns the next available relationship ID.
     *
     * @return string The next rId (e.g., "rId5").
     */
    public function nextRelationshipId(): string
    {
        return 'rId' . (++$this->relationshipIdCounter);
    }

    /**
     * Returns the next available image number.
     *
     * @return int The next image number.
     */
    public function nextImageNumber(): int
    {
        return ++$this->imageCounter;
    }

    /**
     * Returns the next available header/footer file number.
     *
     * @return int The next header/footer number.
     */
    public function nextHeaderFooterNumber(): int
    {
        return ++$this->headerFooterCounter;
    }

    /**
     * Returns the next available style ID for renamed styles.
     *
     * @return int The next style ID (starts from 1000).
     */
    public function nextStyleId(): int
    {
        return ++$this->styleIdCounter;
    }

    /**
     * Returns the next available numId.
     *
     * @return int The next numId.
     */
    public function nextNumId(): int
    {
        return ++$this->numIdCounter;
    }

    /**
     * Returns the next available abstractNumId.
     *
     * @return int The next abstractNumId.
     */
    public function nextAbstractNumId(): int
    {
        return ++$this->abstractNumIdCounter;
    }

    /**
     * Returns the next available drawing object property ID.
     *
     * @return int The next docPr id.
     */
    public function nextDocPrId(): int
    {
        return ++$this->docPrIdCounter;
    }

    /**
     * Returns the next available bookmark ID.
     *
     * @return int The next bookmark id.
     */
    public function nextBookmarkId(): int
    {
        return ++$this->bookmarkIdCounter;
    }
}
