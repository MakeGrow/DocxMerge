<?php

declare(strict_types=1);

namespace DocxMerge\ContentTypes;

use DocxMerge\Tracking\IdTracker;
use DocxMerge\Xml\XmlHelper;
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
     * Ensures all parts in the ZIP have entries in [Content_Types].xml.
     *
     * Scans the ZIP archive for media files and header/footer parts,
     * adding missing Default entries for file extensions and Override
     * entries for specific parts.
     *
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM (modified in place).
     * @param ZipArchive  $targetZip       The target ZIP to scan for parts.
     */
    public function update(
        DOMDocument $contentTypesDom,
        ZipArchive $targetZip,
    ): void {
        $xpath = new DOMXPath($contentTypesDom);
        $xpath->registerNamespace('ct', XmlHelper::NS_CT);

        $this->addDefaultEntriesForMediaFiles($contentTypesDom, $xpath, $targetZip);
        $this->addOverrideEntriesForHeadersAndFooters($contentTypesDom, $xpath, $targetZip);
    }

    /**
     * Registers a required OOXML part in [Content_Types].xml and document.xml.rels.
     *
     * Adds an Override entry for the part in the content types DOM and a
     * Relationship entry in the relationships DOM. Both operations are
     * idempotent: existing entries are not duplicated.
     *
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM (modified in place).
     * @param DOMDocument $relsDom         The document.xml.rels DOM (modified in place).
     * @param string      $partName        The part name (e.g., "/word/numbering.xml").
     * @param string      $contentType     The MIME content type for the Override entry.
     * @param string      $relationshipType The relationship type URI.
     * @param string      $target          The relationship target (e.g., "numbering.xml").
     * @param IdTracker   $idTracker       The ID tracker for generating unique rId values.
     */
    public function registerRequiredPart(
        DOMDocument $contentTypesDom,
        DOMDocument $relsDom,
        string $partName,
        string $contentType,
        string $relationshipType,
        string $target,
        IdTracker $idTracker,
    ): void {
        $this->addOverrideIfMissing($contentTypesDom, $partName, $contentType);
        $this->addRelationshipIfMissing($relsDom, $relationshipType, $target, $idTracker);
    }

    /**
     * Adds an Override entry to [Content_Types].xml if one does not already exist.
     *
     * @param DOMDocument $dom         The [Content_Types].xml DOM.
     * @param string      $partName    The part name for the Override entry.
     * @param string      $contentType The MIME content type.
     */
    private function addOverrideIfMissing(
        DOMDocument $dom,
        string $partName,
        string $contentType,
    ): void {
        $root = $dom->documentElement;
        if ($root === null) {
            return;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ct', XmlHelper::NS_CT);

        $existing = $xpath->query('//ct:Override[@PartName="' . $partName . '"]');
        if ($existing !== false && $existing->length > 0) {
            return;
        }

        $element = $dom->createElementNS(XmlHelper::NS_CT, 'Override');
        $element->setAttribute('PartName', $partName);
        $element->setAttribute('ContentType', $contentType);
        $root->appendChild($element);
    }

    /**
     * Adds a Relationship entry to the rels DOM if one with the same Type does not exist.
     *
     * @param DOMDocument $dom              The relationships DOM.
     * @param string      $relationshipType The relationship type URI.
     * @param string      $target           The relationship target.
     * @param IdTracker   $idTracker        The ID tracker for generating unique rId values.
     */
    private function addRelationshipIfMissing(
        DOMDocument $dom,
        string $relationshipType,
        string $target,
        IdTracker $idTracker,
    ): void {
        $root = $dom->documentElement;
        if ($root === null) {
            return;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('rel', XmlHelper::NS_REL);

        $existing = $xpath->query('//rel:Relationship[@Type="' . $relationshipType . '"]');
        if ($existing !== false && $existing->length > 0) {
            return;
        }

        $element = $dom->createElementNS(XmlHelper::NS_REL, 'Relationship');
        $element->setAttribute('Id', $idTracker->nextRelationshipId());
        $element->setAttribute('Type', $relationshipType);
        $element->setAttribute('Target', $target);
        $root->appendChild($element);
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

            $element = $dom->createElementNS(XmlHelper::NS_CT, 'Default');
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

            $element = $dom->createElementNS(XmlHelper::NS_CT, 'Override');
            $element->setAttribute('PartName', $partName);
            $element->setAttribute('ContentType', $contentType);
            $root->appendChild($element);
        }
    }
}
