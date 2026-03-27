<?php

declare(strict_types=1);

namespace DocxMerge\Exception;

/**
 * Base exception for all DocxMerge errors.
 *
 * All package-specific exceptions extend this class, allowing callers
 * to catch a single type for any DocxMerge-related failure.
 *
 * @codeCoverageIgnore
 */
abstract class DocxMergeException extends \RuntimeException
{
}
