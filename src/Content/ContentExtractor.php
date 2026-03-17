<?php

declare(strict_types=1);

namespace DocxMerge\Content;

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Exception\InvalidSourceException;
use DOMDocument;

/**
 * Extracts body content from a source DOCX document.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see ContentExtractorInterface
 */
final class ContentExtractor implements ContentExtractorInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws InvalidSourceException If sectionIndex exceeds available sections.
     */
    public function extract(
        DOMDocument $sourceDom,
        ?int $sectionIndex = null,
    ): ExtractedContent {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }

    /**
     * {@inheritdoc}
     */
    public function countSections(DOMDocument $sourceDom): int
    {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
