<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Tracking\IdTracker;
use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Merges numbering definitions from a source DOCX into a target DOCX.
 *
 * Filters numbering definitions to include only those actually referenced
 * in the extracted content, assigns new IDs via IdTracker, and maintains
 * the OOXML ordering requirement (all w:abstractNum before all w:num).
 *
 * @see NumberingMergerInterface
 */
final class NumberingMerger implements NumberingMergerInterface
{
    /**
     * Builds a mapping from source numbering IDs to target IDs.
     *
     * Scans the extracted content XML for w:numId references, then maps
     * only the source numbering definitions that are actually used. Each
     * mapped definition receives a new ID from the IdTracker.
     *
     * @param DOMDocument $sourceNumberingDom The source numbering.xml DOM.
     * @param DOMDocument $targetNumberingDom The target numbering.xml DOM.
     * @param string      $contentXml         The extracted content XML (to filter used numIds).
     * @param IdTracker   $idTracker          Shared ID counters.
     *
     * @return NumberingMap Mapping of old IDs to new IDs.
     */
    public function buildMap(
        DOMDocument $sourceNumberingDom,
        DOMDocument $targetNumberingDom,
        string $contentXml,
        IdTracker $idTracker,
    ): NumberingMap {
        $usedNumIds = $this->extractUsedNumIds($contentXml);

        $sourceXpath = new DOMXPath($sourceNumberingDom);
        $sourceXpath->registerNamespace('w', XmlHelper::NS_W);

        // --- Phase 1: Build index of source w:num elements by numId ---
        /** @var array<int, DOMElement> $sourceNumsByNumId */
        $sourceNumsByNumId = [];
        $numNodes = $sourceXpath->query('//w:num');
        if ($numNodes !== false) {
            foreach ($numNodes as $numNode) {
                // @codeCoverageIgnoreStart
                if (!$numNode instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $numId = (int) $numNode->getAttributeNS(XmlHelper::NS_W, 'numId');
                $sourceNumsByNumId[$numId] = $numNode;
            }
        }

        // --- Phase 2: Build index of source w:abstractNum elements by abstractNumId ---
        /** @var array<int, DOMElement> $sourceAbstractNumsById */
        $sourceAbstractNumsById = [];
        $abstractNumNodes = $sourceXpath->query('//w:abstractNum');
        if ($abstractNumNodes !== false) {
            foreach ($abstractNumNodes as $abstractNode) {
                // @codeCoverageIgnoreStart
                if (!$abstractNode instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $absId = (int) $abstractNode->getAttributeNS(XmlHelper::NS_W, 'abstractNumId');
                $sourceAbstractNumsById[$absId] = $abstractNode;
            }
        }

        // --- Phase 3: Map used numIds and their abstractNums to new IDs ---
        /** @var array<int, int> $numMap */
        $numMap = [];
        /** @var array<int, int> $abstractNumMap */
        $abstractNumMap = [];
        /** @var list<DOMElement> $abstractNumNodesToImport */
        $abstractNumNodesToImport = [];
        /** @var list<DOMElement> $numNodesToImport */
        $numNodesToImport = [];

        foreach ($usedNumIds as $oldNumId) {
            if (!isset($sourceNumsByNumId[$oldNumId])) {
                continue;
            }

            $numElement = $sourceNumsByNumId[$oldNumId];
            $newNumId = $idTracker->nextNumId();
            $numMap[$oldNumId] = $newNumId;

            // Find the abstractNumId referenced by this w:num
            $abstractNumIdNode = $sourceXpath->query('w:abstractNumId/@w:val', $numElement);
            if ($abstractNumIdNode === false || $abstractNumIdNode->length === 0) {
                continue;
            }

            $oldAbstractNumId = (int) ($abstractNumIdNode->item(0)->nodeValue ?? '0');

            // Map abstractNum if not already mapped
            if (!isset($abstractNumMap[$oldAbstractNumId])) {
                $newAbstractNumId = $idTracker->nextAbstractNumId();
                $abstractNumMap[$oldAbstractNumId] = $newAbstractNumId;

                if (isset($sourceAbstractNumsById[$oldAbstractNumId])) {
                    // Clone the abstractNum node and update its ID
                    $clonedAbstractNum = $sourceAbstractNumsById[$oldAbstractNumId]->cloneNode(true);
                    // @codeCoverageIgnoreStart
                    if (!$clonedAbstractNum instanceof DOMElement) {
                        continue;
                    }
                    // @codeCoverageIgnoreEnd
                    $clonedAbstractNum->setAttributeNS(XmlHelper::NS_W, 'w:abstractNumId', (string) $newAbstractNumId);
                    $abstractNumNodesToImport[] = $clonedAbstractNum;
                }
            }

            // Clone the num node and update its IDs
            $clonedNum = $numElement->cloneNode(true);
            // @codeCoverageIgnoreStart
            if (!$clonedNum instanceof DOMElement) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $clonedNum->setAttributeNS(XmlHelper::NS_W, 'w:numId', (string) $newNumId);

            // Update the abstractNumId reference inside the cloned num
            $ownerDoc = $clonedNum->ownerDocument;
            // @codeCoverageIgnoreStart
            if (!$ownerDoc instanceof DOMDocument) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $clonedXpath = new DOMXPath($ownerDoc);
            $clonedXpath->registerNamespace('w', XmlHelper::NS_W);
            $clonedAbsIdNodes = $clonedXpath->query('w:abstractNumId', $clonedNum);
            if ($clonedAbsIdNodes !== false && $clonedAbsIdNodes->length > 0) {
                $absIdElement = $clonedAbsIdNodes->item(0);
                // @codeCoverageIgnoreStart
                if (!$absIdElement instanceof DOMElement) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $absIdElement->setAttributeNS(XmlHelper::NS_W, 'w:val', (string) $abstractNumMap[$oldAbstractNumId]);
            }

            $numNodesToImport[] = $clonedNum;
        }

        return new NumberingMap(
            abstractNumMap: $abstractNumMap,
            numMap: $numMap,
            abstractNumNodes: $abstractNumNodesToImport,
            numNodes: $numNodesToImport,
        );
    }

    /**
     * Merges mapped numbering definitions into the target DOM.
     *
     * Inserts w:abstractNum nodes before the first w:num element to maintain
     * OOXML ordering. Appends w:num nodes after all existing content.
     *
     * @param DOMDocument $targetNumberingDom The target numbering.xml DOM (modified in place).
     * @param NumberingMap $numberingMap       The computed numbering map.
     *
     * @return int Number of definitions imported.
     */
    public function merge(
        DOMDocument $targetNumberingDom,
        NumberingMap $numberingMap,
    ): int {
        $root = $targetNumberingDom->documentElement;
        if ($root === null) {
            return 0;
        }

        $targetXpath = new DOMXPath($targetNumberingDom);
        $targetXpath->registerNamespace('w', XmlHelper::NS_W);

        $count = 0;

        // Find the first w:num in target to use as insertion reference for abstractNums
        $firstNum = $targetXpath->query('w:num', $root);
        $firstNumNode = ($firstNum !== false && $firstNum->length > 0) ? $firstNum->item(0) : null;
        // Narrow type for PHPStan: item(0) can return DOMNameSpaceNode, but w:num is always DOMElement
        $referenceNode = $firstNumNode instanceof DOMElement ? $firstNumNode : null;

        // Import abstractNum nodes before the first w:num
        foreach ($numberingMap->abstractNumNodes as $abstractNumNode) {
            $imported = $targetNumberingDom->importNode($abstractNumNode, true);
            if ($referenceNode !== null) {
                $root->insertBefore($imported, $referenceNode);
            } else {
                $root->appendChild($imported);
            }
            $count++;
        }

        // Import num nodes at the end
        foreach ($numberingMap->numNodes as $numNode) {
            $imported = $targetNumberingDom->importNode($numNode, true);
            $root->appendChild($imported);
            $count++;
        }

        return $count;
    }

    /**
     * Extracts numId values referenced in the content XML.
     *
     * Uses regex to find all w:numId w:val="N" references in the content
     * to determine which numbering definitions are actually used.
     *
     * @param string $contentXml The extracted content XML string.
     *
     * @return list<int> The list of unique numId values found.
     */
    private function extractUsedNumIds(string $contentXml): array
    {
        $matches = [];
        preg_match_all('/w:numId\s+w:val="(\d+)"/', $contentXml, $matches);

        if ($matches[1] === []) {
            return [];
        }

        $ids = array_map('intval', $matches[1]);

        return array_values(array_unique($ids));
    }
}
