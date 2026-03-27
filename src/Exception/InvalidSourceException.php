<?php

declare(strict_types=1);

namespace DocxMerge\Exception;

/**
 * Thrown when a source file is not found, not readable, not a valid DOCX, or the section index is out of bounds.
 *
 * @codeCoverageIgnore
 */
final class InvalidSourceException extends DocxMergeException
{
}
