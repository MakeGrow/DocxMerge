<?php

declare(strict_types=1);

namespace DocxMerge\ContentTypes;

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
}
