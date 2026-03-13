<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

use DOMDocument;
use ZipArchive;

/**
 * Cached representation of a source DOCX document.
 *
 * Holds the opened ZIP archive and all parsed DOM trees needed
 * during the merge pipeline. Created once per unique source path
 * and reused across markers referencing the same file.
 */
final class SourceDocument
{
    /**
     * @param ZipArchive $zip The opened source ZIP archive.
     * @param DOMDocument $documentDom The parsed word/document.xml DOM.
     * @param DOMDocument|null $stylesDom The parsed word/styles.xml DOM, or null if absent.
     * @param DOMDocument|null $numberingDom The parsed word/numbering.xml DOM, or null if absent.
     * @param DOMDocument $relsDom The parsed word/_rels/document.xml.rels DOM.
     * @param int $sectionCount Number of sections in the source document.
     */
    public function __construct(
        public readonly ZipArchive $zip,
        public readonly DOMDocument $documentDom,
        public readonly ?DOMDocument $stylesDom,
        public readonly ?DOMDocument $numberingDom,
        public readonly DOMDocument $relsDom,
        public readonly int $sectionCount,
    ) {
    }
}
