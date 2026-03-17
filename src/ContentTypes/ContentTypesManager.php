<?php

declare(strict_types=1);

namespace DocxMerge\ContentTypes;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Maintains [Content_Types].xml in a DOCX archive.
 *
 * Scans the ZIP for media files and header/footer parts, adding Default
 * entries for file extensions and Override entries for specific parts
 * that are not yet registered.
 *
 * @see ContentTypesManagerInterface
 */
final class ContentTypesManager implements ContentTypesManagerInterface
{
    /** Content Types XML namespace URI. */
    private const NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /**
     * Maps file extensions to their MIME content types.
     *
     * @var array<string, string>
     */
    private const EXTENSION_CONTENT_TYPES = [
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'emf' => 'image/x-emf',
        'wmf' => 'image/x-wmf',
        'tiff' => 'image/tiff',
        'bmp' => 'image/bmp',
    ];

    /** Content type for header parts. */
    private const HEADER_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml';

    /** Content type for footer parts. */
    private const FOOTER_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml';

    /**
     * {@inheritDoc}
     *
     * Scans the ZIP archive for media files and header/footer parts,
     * adding missing Default and Override entries to [Content_Types].xml.
     *
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM (modified in place).
     * @param ZipArchive  $targetZip       The target ZIP to scan for parts.
     */
    public function update(
        DOMDocument $contentTypesDom,
        ZipArchive $targetZip,
    ): void {
        $xpath = new DOMXPath($contentTypesDom);
        $xpath->registerNamespace('ct', self::NS_CT);

        $this->addDefaultEntriesForMediaFiles($contentTypesDom, $xpath, $targetZip);
        $this->addOverrideEntriesForHeadersAndFooters($contentTypesDom, $xpath, $targetZip);
    }

    /**
     * Scans ZIP for media files and adds Default entries for new extensions.
     *
     * @param DOMDocument $dom       The [Content_Types].xml DOM.
     * @param DOMXPath    $xpath     XPath configured with the ct namespace.
     * @param ZipArchive  $targetZip The ZIP to scan.
     */
    private function addDefaultEntriesForMediaFiles(
        DOMDocument $dom,
        DOMXPath $xpath,
        ZipArchive $targetZip,
    ): void {
        $root = $dom->documentElement;
        if ($root === null) {
            return;
        }

        /** @var array<string, true> $processedExtensions */
        $processedExtensions = [];

        for ($i = 0; $i < $targetZip->numFiles; $i++) {
            $name = $targetZip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Only consider files in word/media/
            if (!str_starts_with($name, 'word/media/')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($extension === '' || isset($processedExtensions[$extension])) {
                continue;
            }
            $processedExtensions[$extension] = true;

            // Check if a Default entry already exists for this extension
            $existing = $xpath->query('//ct:Default[@Extension="' . $extension . '"]');
            if ($existing !== false && $existing->length > 0) {
                continue;
            }

            $contentType = self::EXTENSION_CONTENT_TYPES[$extension] ?? 'application/octet-stream';

            $element = $dom->createElementNS(self::NS_CT, 'Default');
            $element->setAttribute('Extension', $extension);
            $element->setAttribute('ContentType', $contentType);
            $root->appendChild($element);
        }
    }

    /**
     * Scans ZIP for header and footer parts and adds Override entries.
     *
     * @param DOMDocument $dom       The [Content_Types].xml DOM.
     * @param DOMXPath    $xpath     XPath configured with the ct namespace.
     * @param ZipArchive  $targetZip The ZIP to scan.
     */
    private function addOverrideEntriesForHeadersAndFooters(
        DOMDocument $dom,
        DOMXPath $xpath,
        ZipArchive $targetZip,
    ): void {
        $root = $dom->documentElement;
        if ($root === null) {
            return;
        }

        for ($i = 0; $i < $targetZip->numFiles; $i++) {
            $name = $targetZip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Match word/headerN.xml or word/footerN.xml
            if (!preg_match('#^word/(header|footer)\d+\.xml$#', $name, $matches)) {
                continue;
            }

            $partName = '/' . $name;
            $type = $matches[1];

            // Check if an Override already exists for this part
            $existing = $xpath->query('//ct:Override[@PartName="' . $partName . '"]');
            if ($existing !== false && $existing->length > 0) {
                continue;
            }

            $contentType = $type === 'header'
                ? self::HEADER_CONTENT_TYPE
                : self::FOOTER_CONTENT_TYPE;

            $element = $dom->createElementNS(self::NS_CT, 'Override');
            $element->setAttribute('PartName', $partName);
            $element->setAttribute('ContentType', $contentType);
            $root->appendChild($element);
        }
    }
}
