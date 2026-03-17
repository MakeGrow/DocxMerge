<?php

declare(strict_types=1);

namespace DocxMerge\Numbering;

use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Resequences numbering IDs after all merge passes are complete.
 *
 * Rebuilds the numbering.xml DOM by removing all w:abstractNum and w:num
 * elements, then re-inserting them in the correct OOXML order (abstractNum
 * first, num second) with sequential IDs. Updates all w:numId references
 * in the document DOM to match the new numbering.
 *
 * @see NumberingResequencerInterface
 */
final class NumberingResequencer implements NumberingResequencerInterface
{
    /**
     * Renumbers all abstractNum and num IDs sequentially and enforces DOM ordering.
     *
     * abstractNumId values are renumbered starting from 0.
     * numId values are renumbered starting from 1.
     * All w:abstractNum elements are placed before all w:num elements.
     * All w:numId references in the document DOM are updated to match.
     *
     * @param DOMDocument $numberingDom The numbering.xml DOM (modified in place).
     * @param DOMDocument $documentDom  The document.xml DOM (modified in place for numId refs).
     *
     * @return void
     */
    public function resequence(
        DOMDocument $numberingDom,
        DOMDocument $documentDom,
    ): void {
        $numberingXpath = new DOMXPath($numberingDom);
        $numberingXpath->registerNamespace('w', XmlHelper::NS_W);

        $documentXpath = new DOMXPath($documentDom);
        $documentXpath->registerNamespace('w', XmlHelper::NS_W);

        $root = $numberingDom->documentElement;
        if ($root === null) {
            return;
        }

        // --- Phase 1: Collect all abstractNum and num elements with their old IDs ---
        // IDs must be captured before removal because DOMElement loses namespace
        // attribute resolution once detached from the document tree.
        /** @var list<array{element: DOMElement, oldId: string}> $abstractNums */
        $abstractNums = [];
        /** @var list<array{element: DOMElement, oldId: string}> $nums */
        $nums = [];

        $abstractNumNodes = $numberingXpath->query('//w:abstractNum');
        if ($abstractNumNodes !== false) {
            foreach ($abstractNumNodes as $node) {
                if ($node instanceof DOMElement) {
                    $abstractNums[] = [
                        'element' => $node,
                        'oldId' => $node->getAttribute('w:abstractNumId'),
                    ];
                }
            }
        }

        $numNodes = $numberingXpath->query('//w:num');
        if ($numNodes !== false) {
            foreach ($numNodes as $node) {
                if ($node instanceof DOMElement) {
                    // Capture the old abstractNumId reference before detaching
                    $abstractNumIdRef = '';
                    foreach ($node->childNodes as $child) {
                        if ($child instanceof DOMElement && $child->localName === 'abstractNumId') {
                            $abstractNumIdRef = $child->getAttribute('w:val');
                            break;
                        }
                    }

                    $nums[] = [
                        'element' => $node,
                        'oldId' => $node->getAttribute('w:numId'),
                        'oldAbstractNumIdRef' => $abstractNumIdRef,
                    ];
                }
            }
        }

        // --- Phase 2: Build old-to-new ID mappings ---
        /** @var array<string, string> $abstractNumIdMap */
        $abstractNumIdMap = [];
        foreach ($abstractNums as $index => $entry) {
            $abstractNumIdMap[$entry['oldId']] = (string) $index;
        }

        /** @var array<string, string> $numIdMap */
        $numIdMap = [];
        foreach ($nums as $index => $entry) {
            $numIdMap[$entry['oldId']] = (string) ($index + 1);
        }

        // --- Phase 3: Remove all abstractNum and num from parent ---
        foreach ($abstractNums as $entry) {
            $entry['element']->parentNode?->removeChild($entry['element']);
        }
        foreach ($nums as $entry) {
            $entry['element']->parentNode?->removeChild($entry['element']);
        }

        // --- Phase 4: Re-insert with new IDs in correct order ---
        // abstractNum first, renumbered 0, 1, 2, ...
        // setAttributeNS is required because the w: prefixed attributes belong to
        // the WordprocessingML namespace. Using plain setAttribute would create a
        // duplicate non-namespaced attribute instead of updating the existing one.
        foreach ($abstractNums as $entry) {
            $entry['element']->setAttributeNS(XmlHelper::NS_W, 'w:abstractNumId', $abstractNumIdMap[$entry['oldId']]);
            $root->appendChild($entry['element']);
        }

        // num second, renumbered 1, 2, 3, ...
        /** @var array{element: DOMElement, oldId: string, oldAbstractNumIdRef: string} $entry */
        foreach ($nums as $entry) {
            $entry['element']->setAttributeNS(XmlHelper::NS_W, 'w:numId', $numIdMap[$entry['oldId']]);

            // Update w:abstractNumId reference inside each w:num
            if ($entry['oldAbstractNumIdRef'] !== '' && isset($abstractNumIdMap[$entry['oldAbstractNumIdRef']])) {
                foreach ($entry['element']->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->localName === 'abstractNumId') {
                        $child->setAttributeNS(XmlHelper::NS_W, 'w:val', $abstractNumIdMap[$entry['oldAbstractNumIdRef']]);
                        break;
                    }
                }
            }

            $root->appendChild($entry['element']);
        }

        // --- Phase 5: Update numId references in document.xml ---
        $docNumIdNodes = $documentXpath->query('//w:numPr/w:numId');
        if ($docNumIdNodes !== false) {
            foreach ($docNumIdNodes as $docNumIdNode) {
                if ($docNumIdNode instanceof DOMElement) {
                    $oldVal = $docNumIdNode->getAttribute('w:val');
                    if (isset($numIdMap[$oldVal])) {
                        $docNumIdNode->setAttributeNS(XmlHelper::NS_W, 'w:val', $numIdMap[$oldVal]);
                    }
                }
            }
        }
    }
}
