<?php

declare(strict_types=1);

namespace DocxMerge\HeaderFooter;

use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Dto\HeaderFooterMapping;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Copies headers and footers from a source DOCX to a target DOCX.
 *
 * For each header/footer relationship found in the source document.xml.rels,
 * reads the XML content, generates a new sequential filename, adds a new
 * relationship in the target rels DOM, and writes the file to the target ZIP.
 */
final class HeaderFooterCopier implements HeaderFooterCopierInterface
{
    /** @var string Relationship type URI for headers. */
    private const HEADER_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';

    /** @var string Relationship type URI for footers. */
    private const FOOTER_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer';

    /** @var string Package relationships namespace URI. */
    private const RELS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Copies headers and footers from source to target.
     *
     * Scans the source rels DOM for header/footer relationships, reads each
     * file from the source ZIP, writes it to the target ZIP with a new
     * sequential name, and registers a new relationship in the target rels DOM.
     *
     * @param ZipArchive $sourceZip The source ZIP archive.
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param DOMDocument $targetRelsDom The target rels DOM (modified in place).
     * @param DOMDocument $sourceRelsDom The source rels DOM.
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return HeaderFooterMap Mapping of old rIds to new rIds and filenames.
     */
    public function copy(
        ZipArchive $sourceZip,
        ZipArchive $targetZip,
        DOMDocument $targetRelsDom,
        DOMDocument $sourceRelsDom,
        IdTracker $idTracker,
    ): HeaderFooterMap {
        $mappings = [];

        $sourceXpath = new DOMXPath($sourceRelsDom);
        $sourceXpath->registerNamespace('rel', self::RELS_NAMESPACE);

        // Find all header and footer relationships in the source
        $headerRels = $sourceXpath->query(
            '//rel:Relationship[@Type="' . self::HEADER_TYPE . '" or @Type="' . self::FOOTER_TYPE . '"]'
        );

        if ($headerRels === false || $headerRels->length === 0) {
            return new HeaderFooterMap([]);
        }

        foreach ($headerRels as $relNode) {
            assert($relNode instanceof DOMElement);

            $oldId = $relNode->getAttribute('Id');
            $type = $relNode->getAttribute('Type');
            $target = $relNode->getAttribute('Target');
            $isHeader = ($type === self::HEADER_TYPE);

            // Read the header/footer XML from source ZIP
            $sourcePath = 'word/' . $target;
            $content = $sourceZip->getFromName($sourcePath);

            if ($content === false) {
                continue;
            }

            // Generate new sequential filename
            $hfNumber = $idTracker->nextHeaderFooterNumber();
            $prefix = $isHeader ? 'header' : 'footer';
            $newFilename = $prefix . $hfNumber . '.xml';
            $targetPath = 'word/' . $newFilename;

            // Write the file to target ZIP
            $targetZip->addFromString($targetPath, $content);

            // Generate new relationship ID and add to target rels DOM
            $newRelId = $idTracker->nextRelationshipId();
            $this->addRelationship($targetRelsDom, $newRelId, $type, $newFilename);

            $mappings[$oldId] = new HeaderFooterMapping(
                oldId: $oldId,
                newRelId: $newRelId,
                oldTarget: $target,
                newFilename: $newFilename,
                type: 'default',
                isHeader: $isHeader,
            );
        }

        return new HeaderFooterMap($mappings);
    }

    /**
     * Adds a Relationship element to the target rels DOM.
     *
     * @param DOMDocument $relsDom The rels DOM to modify.
     * @param string $id The new relationship ID.
     * @param string $type The relationship type URI.
     * @param string $target The target filename relative to word/.
     */
    private function addRelationship(
        DOMDocument $relsDom,
        string $id,
        string $type,
        string $target,
    ): void {
        $root = $relsDom->documentElement;

        if ($root === null) {
            return;
        }

        $relElement = $relsDom->createElementNS(self::RELS_NAMESPACE, 'Relationship');
        $relElement->setAttribute('Id', $id);
        $relElement->setAttribute('Type', $type);
        $relElement->setAttribute('Target', $target);

        $root->appendChild($relElement);
    }
}
