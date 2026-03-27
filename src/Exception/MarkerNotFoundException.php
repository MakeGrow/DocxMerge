<?php

declare(strict_types=1);

namespace DocxMerge\Exception;

/**
 * Thrown when a marker is not found in the template document.
 *
 * Only thrown when strict marker mode is enabled via MergeOptions.
 *
 * @codeCoverageIgnore
 */
final class MarkerNotFoundException extends DocxMergeException
{
}
