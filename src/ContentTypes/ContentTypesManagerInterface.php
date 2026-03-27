<?php

declare(strict_types=1);

namespace DocxMerge\ContentTypes;

use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use ZipArchive;

/**
 * Contract for maintaining [Content_Types].xml in a DOCX archive.
 *
 * Implementations must ensure all parts in the ZIP have appropriate entries,
 * adding Default entries for file extensions and Override entries for
 * specific parts like headers and footers.
 */
interface ContentTypesManagerInterface
{
    /**
     * Ensures all parts in the ZIP have entries in [Content_Types].xml.
     *
     * Adds Default entries for file extensions (png, jpeg, gif, emf, wmf).
     * Adds Override entries for specific parts (headers, footers).
     *
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM (modified in place).
     * @param ZipArchive $targetZip The target ZIP to scan for parts.
     *
     * @return void
     */
    public function update(
        DOMDocument $contentTypesDom,
        ZipArchive $targetZip,
    ): void;

    /**
     * Registers a required OOXML part in [Content_Types].xml and document.xml.rels.
     *
     * Adds an Override entry for the part in the content types DOM and a
     * Relationship entry in the relationships DOM. Both operations are
     * idempotent: existing entries are not duplicated.
     *
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM (modified in place).
     * @param DOMDocument $relsDom         The document.xml.rels DOM (modified in place).
     * @param string      $partName        The part name (e.g., "/word/numbering.xml").
     * @param string      $contentType     The MIME content type for the Override entry.
     * @param string      $relationshipType The relationship type URI.
     * @param string      $target          The relationship target (e.g., "numbering.xml").
     * @param IdTracker   $idTracker       The ID tracker for generating unique rId values.
     */
    public function registerRequiredPart(
        DOMDocument $contentTypesDom,
        DOMDocument $relsDom,
        string $partName,
        string $contentType,
        string $relationshipType,
        string $target,
        IdTracker $idTracker,
    ): void;
}
