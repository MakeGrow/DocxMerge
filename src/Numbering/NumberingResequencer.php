<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DOMDocument;

/**
 * Resequences numbering IDs after all merge passes are complete.
 *
 * Stub implementation — real logic added in Phase 3.
 *
 * @see NumberingResequencerInterface
 */
final class NumberingResequencer implements NumberingResequencerInterface
{
    /**
     * {@inheritdoc}
     */
    public function resequence(
        DOMDocument $numberingDom,
        DOMDocument $documentDom,
    ): void {
        throw new \LogicException('Not implemented — Phase 3 GREEN task.');
    }
}
