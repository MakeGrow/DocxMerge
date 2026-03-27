<?php

declare(strict_types=1);

namespace DocxMerge\Exception;

/**
 * Thrown when malformed XML is encountered in a document part.
 *
 * Wraps libxml errors collected during DOM parsing.
 *
 * @codeCoverageIgnore
 */
final class XmlParseException extends DocxMergeException
{
}
