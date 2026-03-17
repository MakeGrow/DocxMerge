<?php

declare(strict_types=1);

namespace DocxMerge\ContentTypes;

use DOMDocument;
use ZipArchive;

/**
 * Maintains [Content_Types].xml in a DOCX archive.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see ContentTypesManagerInterface
 */
final class ContentTypesManager implements ContentTypesManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function update(
        DOMDocument $contentTypesDom,
        ZipArchive $targetZip,
    ): void {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
