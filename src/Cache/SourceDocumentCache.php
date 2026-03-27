<?php

declare(strict_types=1);

namespace DocxMerge\Cache;

use DocxMerge\Content\ContentExtractor;
use DocxMerge\Dto\SourceDocument;
use DocxMerge\Exception\InvalidSourceException;
use DocxMerge\Xml\XmlHelper;
use ZipArchive;

/**
 * Caches parsed source documents to avoid re-opening the same ZIP.
 *
 * When multiple markers reference the same source file, the ZIP is
 * opened and parsed only once. Subsequent calls return the cached instance.
 * Uses realpath() for cache keys to avoid duplicates from different path forms.
 */
final class SourceDocumentCache
{
    /** @var array<string, SourceDocument> */
    private array $cache = [];

    private readonly XmlHelper $xmlHelper;

    private readonly ContentExtractor $contentExtractor;

    /**
     * @param XmlHelper|null $xmlHelper XML helper for DOM creation. Defaults to a new instance.
     * @param ContentExtractor|null $contentExtractor Extractor for counting sections. Defaults to a new instance.
     */
    public function __construct(
        ?XmlHelper $xmlHelper = null,
        ?ContentExtractor $contentExtractor = null,
    ) {
        $this->xmlHelper = $xmlHelper ?? new XmlHelper();
        $this->contentExtractor = $contentExtractor ?? new ContentExtractor();
    }

    /**
     * Returns a cached SourceDocument for the given path.
     *
     * On first access, opens the ZIP, parses all relevant XML parts
     * (document.xml, styles.xml, numbering.xml, document.xml.rels),
     * counts sections, and caches the result. On subsequent accesses,
     * returns the cached instance.
     *
     * @param string $sourcePath Absolute path to the source DOCX file.
     *
     * @return SourceDocument Cached source document with all parsed DOMs.
     *
     * @throws InvalidSourceException If the file does not exist or is not a valid DOCX.
     */
    public function get(string $sourcePath): SourceDocument
    {
        // Normalize the path to avoid cache misses for the same file via different paths.
        $cacheKey = realpath($sourcePath);

        if ($cacheKey === false) {
            throw new InvalidSourceException(
                "Source file does not exist: {$sourcePath}"
            );
        }

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $zip = new ZipArchive();
        $result = $zip->open($cacheKey, ZipArchive::RDONLY);

        if ($result !== true) {
            throw new InvalidSourceException(
                "Failed to open source DOCX as ZIP: {$sourcePath}"
            );
        }

        $documentDom = $this->readPartAsDom($zip, 'word/document.xml', $sourcePath);
        $relsDom = $this->readPartAsDom($zip, 'word/_rels/document.xml.rels', $sourcePath);

        // styles.xml and numbering.xml are optional parts
        $stylesDom = $this->readOptionalPartAsDom($zip, 'word/styles.xml');
        $numberingDom = $this->readOptionalPartAsDom($zip, 'word/numbering.xml');

        $sectionCount = $this->contentExtractor->countSections($documentDom);

        $sourceDocument = new SourceDocument(
            zip: $zip,
            documentDom: $documentDom,
            stylesDom: $stylesDom,
            numberingDom: $numberingDom,
            relsDom: $relsDom,
            sectionCount: $sectionCount,
        );

        $this->cache[$cacheKey] = $sourceDocument;

        return $sourceDocument;
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

    /**
     * Reads a required ZIP part and parses it into a DOMDocument.
     *
     * @param ZipArchive $zip The opened ZIP archive.
     * @param string $partName The ZIP entry name (e.g. "word/document.xml").
     * @param string $sourcePath The original file path for error messages.
     *
     * @return \DOMDocument The parsed DOM.
     *
     * @throws InvalidSourceException If the part is missing or cannot be parsed.
     */
    private function readPartAsDom(ZipArchive $zip, string $partName, string $sourcePath): \DOMDocument
    {
        $xml = $zip->getFromName($partName);

        if ($xml === false) {
            throw new InvalidSourceException(
                "Required part '{$partName}' not found in source DOCX: {$sourcePath}"
            );
        }

        return $this->xmlHelper->createDom($xml);
    }

    /**
     * Reads an optional ZIP part and parses it into a DOMDocument if present.
     *
     * @param ZipArchive $zip The opened ZIP archive.
     * @param string $partName The ZIP entry name.
     *
     * @return \DOMDocument|null The parsed DOM, or null if the part does not exist.
     */
    private function readOptionalPartAsDom(ZipArchive $zip, string $partName): ?\DOMDocument
    {
        $xml = $zip->getFromName($partName);

        if ($xml === false) {
            return null;
        }

        return $this->xmlHelper->createDom($xml);
    }
}
