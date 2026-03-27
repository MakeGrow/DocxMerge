<?php

declare(strict_types=1);

namespace DocxMerge\Remapping;

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\StyleMap;
use DocxMerge\Tracking\IdTracker;
use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Remaps all IDs in extracted content before insertion into the target.
 *
 * Processes relationship IDs (r:embed, r:id), style IDs (w:pStyle, w:rStyle,
 * w:tblStyle), numbering IDs (w:numId), drawing object IDs (wp:docPr), and
 * bookmark IDs (w:bookmarkStart, w:bookmarkEnd) to prevent collisions with
 * existing IDs in the target document.
 *
 * @see IdRemapperInterface
 */
final class IdRemapper implements IdRemapperInterface
{
    /**
     * Remaps all IDs in the extracted content DOM nodes.
     *
     * Processes in order:
     * 1. r:embed and r:id attributes (relationship IDs)
     * 2. w:pStyle, w:rStyle, w:tblStyle attributes (style IDs)
     * 3. w:numId values inside w:numPr (numbering IDs)
     * 4. wp:docPr id attributes (drawing object IDs)
     * 5. w:bookmarkStart and w:bookmarkEnd w:id attributes (bookmark IDs)
     *
     * @param list<DOMNode> $contentNodes The content nodes to remap (modified in place).
     * @param RelationshipMap $relationshipMap rId mapping.
     * @param StyleMap $styleMap Style ID mapping.
     * @param NumberingMap $numberingMap Numbering ID mapping.
     * @param IdTracker $idTracker Shared ID counters for docPr and bookmarks.
     * @param DOMDocument $targetDom The target document DOM (for XPath context).
     */
    public function remap(
        array $contentNodes,
        RelationshipMap $relationshipMap,
        StyleMap $styleMap,
        NumberingMap $numberingMap,
        IdTracker $idTracker,
        DOMDocument $targetDom,
    ): void {
        $xpath = new DOMXPath($targetDom);
        $xpath->registerNamespace('w', XmlHelper::NS_W);
        $xpath->registerNamespace('r', XmlHelper::NS_R);
        $xpath->registerNamespace('wp', XmlHelper::NS_WP);
        $xpath->registerNamespace('a', XmlHelper::NS_A);

        foreach ($contentNodes as $node) {
            $this->remapRelationshipIds($node, $xpath, $relationshipMap);
            $this->remapStyleIds($node, $xpath, $styleMap);
            $this->remapNumberingIds($node, $xpath, $numberingMap);
            $this->remapDocPrIds($node, $xpath, $idTracker);
            $this->remapBookmarkIds($node, $xpath, $idTracker);
        }
    }

    /**
     * Remaps r:embed and r:id attributes using the relationship map.
     *
     * @param DOMNode $node The content node to scan.
     * @param DOMXPath $xpath The XPath instance with registered namespaces.
     * @param RelationshipMap $relationshipMap The rId mapping.
     */
    private function remapRelationshipIds(
        DOMNode $node,
        DOMXPath $xpath,
        RelationshipMap $relationshipMap,
    ): void {
        // Remap r:embed attributes (images in drawings)
        $embeds = $xpath->query('.//*/@r:embed', $node);
        if ($embeds !== false) {
            foreach ($embeds as $attr) {
                $oldId = $attr->nodeValue ?? '';
                $newId = $relationshipMap->getNewId($oldId);
                if ($newId !== null) {
                    $attr->nodeValue = $newId;
                }
            }
        }

        // Remap r:id attributes (hyperlinks, etc.)
        $rIds = $xpath->query('.//*/@r:id', $node);
        if ($rIds !== false) {
            foreach ($rIds as $attr) {
                $oldId = $attr->nodeValue ?? '';
                $newId = $relationshipMap->getNewId($oldId);
                if ($newId !== null) {
                    $attr->nodeValue = $newId;
                }
            }
        }
    }

    /**
     * Remaps w:pStyle, w:rStyle, and w:tblStyle attributes using the style map.
     *
     * @param DOMNode $node The content node to scan.
     * @param DOMXPath $xpath The XPath instance with registered namespaces.
     * @param StyleMap $styleMap The style ID mapping.
     */
    private function remapStyleIds(
        DOMNode $node,
        DOMXPath $xpath,
        StyleMap $styleMap,
    ): void {
        // Remap w:pStyle
        $pStyles = $xpath->query('.//w:pStyle/@w:val', $node);
        if ($pStyles !== false) {
            foreach ($pStyles as $attr) {
                $oldId = $attr->nodeValue ?? '';
                if ($styleMap->hasMapping($oldId)) {
                    $attr->nodeValue = $styleMap->getNewId($oldId);
                }
            }
        }

        // Remap w:rStyle
        $rStyles = $xpath->query('.//w:rStyle/@w:val', $node);
        if ($rStyles !== false) {
            foreach ($rStyles as $attr) {
                $oldId = $attr->nodeValue ?? '';
                if ($styleMap->hasMapping($oldId)) {
                    $attr->nodeValue = $styleMap->getNewId($oldId);
                }
            }
        }

        // Remap w:tblStyle
        $tblStyles = $xpath->query('.//w:tblStyle/@w:val', $node);
        if ($tblStyles !== false) {
            foreach ($tblStyles as $attr) {
                $oldId = $attr->nodeValue ?? '';
                if ($styleMap->hasMapping($oldId)) {
                    $attr->nodeValue = $styleMap->getNewId($oldId);
                }
            }
        }
    }

    /**
     * Remaps w:numId values using the numbering map.
     *
     * Uses direct mapping from old numId to new numId for all w:numId elements
     * found inside w:numPr blocks.
     *
     * @param DOMNode $node The content node to scan.
     * @param DOMXPath $xpath The XPath instance with registered namespaces.
     * @param NumberingMap $numberingMap The numbering ID mapping.
     */
    private function remapNumberingIds(
        DOMNode $node,
        DOMXPath $xpath,
        NumberingMap $numberingMap,
    ): void {
        $numIds = $xpath->query('.//w:numPr/w:numId/@w:val', $node);
        if ($numIds === false) {
            return;
        }

        foreach ($numIds as $attr) {
            $oldNumId = (int) ($attr->nodeValue ?? '0');
            $newNumId = $numberingMap->getNewNumId($oldNumId);
            if ($newNumId !== null) {
                $attr->nodeValue = (string) $newNumId;
            }
        }
    }

    /**
     * Remaps wp:docPr id attributes to unique values.
     *
     * Each wp:docPr element gets a new unique ID from the IdTracker to avoid
     * collisions with existing drawing objects in the target document.
     *
     * @param DOMNode $node The content node to scan.
     * @param DOMXPath $xpath The XPath instance with registered namespaces.
     * @param IdTracker $idTracker The ID tracker for generating new IDs.
     */
    private function remapDocPrIds(
        DOMNode $node,
        DOMXPath $xpath,
        IdTracker $idTracker,
    ): void {
        $docPrs = $xpath->query('.//wp:docPr', $node);
        // @codeCoverageIgnoreStart
        if ($docPrs === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        foreach ($docPrs as $docPr) {
            // @codeCoverageIgnoreStart
            if (!$docPr instanceof DOMElement) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $newId = $idTracker->nextDocPrId();
            $docPr->setAttribute('id', (string) $newId);
        }
    }

    /**
     * Remaps w:bookmarkStart and w:bookmarkEnd w:id attributes.
     *
     * Ensures that paired bookmarkStart and bookmarkEnd elements receive the
     * same new ID by tracking the old-to-new mapping within each content node.
     *
     * @param DOMNode $node The content node to scan.
     * @param DOMXPath $xpath The XPath instance with registered namespaces.
     * @param IdTracker $idTracker The ID tracker for generating new IDs.
     */
    private function remapBookmarkIds(
        DOMNode $node,
        DOMXPath $xpath,
        IdTracker $idTracker,
    ): void {
        /** @var array<string, int> $bookmarkMap Maps old bookmark ID to new bookmark ID */
        $bookmarkMap = [];

        // Process bookmarkStart elements first to build the mapping
        $starts = $xpath->query('.//w:bookmarkStart', $node);
        if ($starts !== false) {
            foreach ($starts as $start) {
                // @codeCoverageIgnoreStart
                if (!$start instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $oldId = $start->getAttributeNS(XmlHelper::NS_W, 'id');
                if ($oldId === '') {
                    continue;
                }

                if (!isset($bookmarkMap[$oldId])) {
                    $bookmarkMap[$oldId] = $idTracker->nextBookmarkId();
                }

                $start->setAttributeNS(XmlHelper::NS_W, 'w:id', (string) $bookmarkMap[$oldId]);
            }
        }

        // Process bookmarkEnd elements using the same mapping
        $ends = $xpath->query('.//w:bookmarkEnd', $node);
        if ($ends !== false) {
            foreach ($ends as $end) {
                // @codeCoverageIgnoreStart
                if (!$end instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $oldId = $end->getAttributeNS(XmlHelper::NS_W, 'id');
                if ($oldId === '') {
                    continue;
                }

                if (!isset($bookmarkMap[$oldId])) {
                    // bookmarkEnd without matching bookmarkStart -- assign a new ID
                    $bookmarkMap[$oldId] = $idTracker->nextBookmarkId();
                }

                $end->setAttributeNS(XmlHelper::NS_W, 'w:id', (string) $bookmarkMap[$oldId]);
            }
        }
    }
}
