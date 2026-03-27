<?php

declare(strict_types=1);

namespace DocxMerge\HeaderFooter;

use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use ZipArchive;

/**
 * Contract for copying headers and footers from a source DOCX to a target DOCX.
 *
 * Implementations must copy header/footer XML, their local .rels files,
 * any images referenced internally, and register new relationships in the
 * target document.xml.rels.
 *
 * @codeCoverageIgnore
 */
interface HeaderFooterCopierInterface
{
    /**
     * Copies headers and footers from source to target.
     *
     * For each header/footer:
     * 1. Reads the XML content and its local .rels file from the source ZIP.
     * 2. Copies any images referenced by the header/footer to the target ZIP.
     * 3. Creates a new local .rels file with remapped image rIds.
     * 4. Updates image references in the header/footer XML.
     * 5. Writes the header/footer XML to the target ZIP with a new sequential name.
     * 6. Adds a relationship in the target document.xml.rels.
     *
     * @param ZipArchive $sourceZip The source ZIP archive.
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param DOMDocument $targetRelsDom The target rels DOM (modified in place).
     * @param DOMDocument $sourceRelsDom The source rels DOM.
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return HeaderFooterMap Mapping of old rIds to new rIds and filenames.
     */
    public function copy(
        ZipArchive $sourceZip,
        ZipArchive $targetZip,
        DOMDocument $targetRelsDom,
        DOMDocument $sourceRelsDom,
        IdTracker $idTracker,
    ): HeaderFooterMap;
}
