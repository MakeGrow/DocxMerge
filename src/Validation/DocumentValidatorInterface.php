<?php

declare(strict_types=1);

namespace DocxMerge\Validation;

use DocxMerge\Dto\ValidationResult;
use DocxMerge\Merge\MergeContext;

/**
 * Contract for validating the consistency of a merged DOCX document.
 *
 * Implementations must check that all rIds resolve, all numIds exist,
 * all style references are valid, all header/footer files exist,
 * and all ZIP parts have Content_Types entries.
 */
interface DocumentValidatorInterface
{
    /**
     * Validates the consistency of the merged document.
     *
     * Checks:
     * 1. Every rId in document.xml exists in document.xml.rels.
     * 2. Every Target in document.xml.rels points to an existing file in the ZIP.
     * 3. Every w:numId in paragraphs has a w:num in numbering.xml.
     * 4. Every w:pStyle/w:rStyle references an existing style in styles.xml.
     * 5. Every header/footer file referenced by sectPr exists in the ZIP.
     * 6. Every file in the ZIP has an entry in [Content_Types].xml.
     *
     * @param MergeContext $context The merge context with all DOMs and the ZIP.
     *
     * @return ValidationResult List of errors and warnings found.
     */
    public function validate(MergeContext $context): ValidationResult;
}
