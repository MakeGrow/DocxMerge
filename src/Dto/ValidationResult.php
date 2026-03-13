<?php

declare(strict_types=1);

namespace DocxMerge\Dto;

/**
 * Result of a document integrity validation.
 *
 * Contains lists of errors (structural issues that may cause Word to
 * show a repair prompt) and warnings (potential issues that Word
 * handles gracefully).
 */
final class ValidationResult
{
    /**
     * @param list<string> $errors Critical integrity errors.
     * @param list<string> $warnings Non-critical warnings.
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $warnings,
    ) {
    }

    /**
     * Returns true when no critical errors were found.
     *
     * @return bool True if the document passes validation.
     */
    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }
}
