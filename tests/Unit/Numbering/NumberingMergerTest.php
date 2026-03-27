<?php

declare(strict_types=1);

/**
 * Tests for NumberingMerger.
 *
 * Verifies that numbering definitions are imported from a source document
 * into the target, filtered by actual usage in the extracted content,
 * and inserted in correct OOXML order (abstractNum before num).
 */

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Numbering\NumberingMerger;
use DocxMerge\Tracking\IdTracker;

describe('NumberingMerger', function (): void {
    describe('buildMap()', function (): void {
        it('maps abstractNum and num definitions referenced in the content', function (): void {
            // Arrange
            $merger = new NumberingMerger();
            $sourceNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '</w:numbering>'
            );
            $targetNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            // Content references numId="1"
            $contentXml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr>'
                . '<w:r><w:t>Item</w:t></w:r></w:p>';
            $idTracker = new IdTracker();

            // Act
            $map = $merger->buildMap($sourceNumberingDom, $targetNumberingDom, $contentXml, $idTracker);

            // Assert
            expect($map)->toBeInstanceOf(NumberingMap::class);
            expect($map->getNewNumId(1))->not->toBeNull();
            expect($map->getNewAbstractNumId(0))->not->toBeNull();
        });

        it('returns an empty map when content has no numbering references', function (): void {
            // Arrange
            $merger = new NumberingMerger();
            $sourceNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"/></w:abstractNum>'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '</w:numbering>'
            );
            $targetNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $contentXml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:r><w:t>No lists here</w:t></w:r></w:p>';
            $idTracker = new IdTracker();

            // Act
            $map = $merger->buildMap($sourceNumberingDom, $targetNumberingDom, $contentXml, $idTracker);

            // Assert
            expect($map)->toBeInstanceOf(NumberingMap::class);
            expect($map->numMap)->toBe([]);
            expect($map->abstractNumMap)->toBe([]);
        });

        it('excludes numbering definitions not referenced in the content', function (): void {
            // Arrange
            $merger = new NumberingMerger();
            $sourceNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:abstractNum w:abstractNumId="1">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>'
                . '</w:numbering>'
            );
            $targetNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            // Content only references numId="2", not numId="1"
            $contentXml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:pPr><w:numPr><w:numId w:val="2"/></w:numPr></w:pPr>'
                . '<w:r><w:t>Item</w:t></w:r></w:p>';
            $idTracker = new IdTracker();

            // Act
            $map = $merger->buildMap($sourceNumberingDom, $targetNumberingDom, $contentXml, $idTracker);

            // Assert -- numId 1 should not be mapped, numId 2 should be
            expect($map->getNewNumId(1))->toBeNull();
            expect($map->getNewNumId(2))->not->toBeNull();
        });
    });

    describe('merge()', function (): void {
        it('returns zero when the target numbering DOM has no document element', function (): void {
            // Arrange
            $merger = new NumberingMerger();
            $emptyTargetDom = new DOMDocument();
            $numberingMap = new NumberingMap(
                abstractNumMap: [],
                numMap: [],
                abstractNumNodes: [],
                numNodes: [],
            );

            // Act
            $result = $merger->merge($emptyTargetDom, $numberingMap);

            // Assert
            expect($result)->toBe(0);
        });

        it('inserts abstractNum before num elements in the target DOM', function (): void {
            // Arrange
            $merger = new NumberingMerger();
            $sourceNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '</w:numbering>'
            );
            $targetNumberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="10">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="10"><w:abstractNumId w:val="10"/></w:num>'
                . '</w:numbering>'
            );
            $contentXml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr>'
                . '<w:r><w:t>Item</w:t></w:r></w:p>';
            $idTracker = new IdTracker();
            $map = $merger->buildMap($sourceNumberingDom, $targetNumberingDom, $contentXml, $idTracker);

            // Act
            $imported = $merger->merge($targetNumberingDom, $map);

            // Assert -- at least one definition imported
            expect($imported)->toBeGreaterThan(0);

            // Verify ordering: all abstractNum should precede all num
            $xpath = createXpathWithNamespaces($targetNumberingDom);
            $children = $xpath->query('/w:numbering/*');
            $seenNum = false;
            for ($i = 0; $i < $children->length; $i++) {
                $child = $children->item($i);
                assert($child instanceof DOMElement);
                if ($child->localName === 'num') {
                    $seenNum = true;
                }
                // Once we see a w:num, no w:abstractNum should follow
                if ($seenNum && $child->localName === 'abstractNum') {
                    expect(true)->toBeFalse('abstractNum found after num -- ordering violated');
                }
            }
        });
    });
});
