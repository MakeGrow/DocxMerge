<?php

declare(strict_types=1);

namespace DocxMerge\Cache;

use DocxMerge\Dto\SourceDocument;
use DocxMerge\Exception\InvalidSourceException;

/**
 * Caches parsed source documents to avoid re-opening the same ZIP.
 *
 * When multiple markers reference the same source file, the ZIP is
 * opened and parsed only once. Subsequent calls return the cached instance.
 */
final class SourceDocumentCache
{
    /** @var array<string, SourceDocument> */
    private array $cache = [];

    /**
     * Returns a cached SourceDocument for the given path.
     *
     * @param string $sourcePath Absolute path to the source DOCX file.
     *
     * @return SourceDocument Cached source document.
     *
     * @throws InvalidSourceException If the file is not a valid DOCX.
     */
    public function get(string $sourcePath): SourceDocument
    {
        if (isset($this->cache[$sourcePath])) {
            return $this->cache[$sourcePath];
        }

        // Stub -- real implementation in a later phase
        throw new InvalidSourceException("Not implemented: {$sourcePath}");
    }

    /**
     * Releases all cached resources.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
    }
}
