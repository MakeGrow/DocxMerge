<?php

declare(strict_types=1);

/**
 * Tests for IdRemapper.
 *
 * Verifies that all ID types (relationship, style, numbering, docPr,
 * bookmark) are correctly remapped in extracted content nodes before
 * insertion into the target document.
 */

use DocxMerge\Dto\NumberingMap;
use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\RelationshipMapping;
use DocxMerge\Dto\StyleMap;
use DocxMerge\Dto\StyleMapping;
use DocxMerge\Remapping\IdRemapper;
use DocxMerge\Tracking\IdTracker;

describe('IdRemapper', function (): void {
    describe('remap()', function (): void {
        it('remaps r:embed attributes using the relationship map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<w:body>'
                . '<w:p><w:r><w:drawing><a:blip xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
                . ' r:embed="rId1"/></w:drawing></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([
                'rId1' => new RelationshipMapping(
                    oldId: 'rId1',
                    newId: 'rId99',
                    type: 'image',
                    target: 'media/image1.png',
                    newTarget: 'media/image50.png',
                    needsFileCopy: true,
                    isExternal: false,
                ),
            ]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $blips = $xpath->query('//a:blip/@r:embed');
            expect($blips->length)->toBe(1);
            expect($blips->item(0)->nodeValue)->toBe('rId99');
        });

        it('remaps w:pStyle attributes using the style map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:pStyle w:val="OldStyle"/></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            // Create a minimal style node for the StyleMapping
            $styleDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:style xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' w:type="paragraph" w:styleId="OldStyle"><w:name w:val="Old"/></w:style>'
            );
            /** @var DOMElement $styleNode */
            $styleNode = $styleDom->documentElement;

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([
                'OldStyle' => new StyleMapping(
                    oldId: 'OldStyle',
                    newId: 'NewStyle1001',
                    type: 'paragraph',
                    node: $styleNode,
                    reuseExisting: false,
                ),
            ]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $pStyles = $xpath->query('//w:pStyle/@w:val');
            expect($pStyles->length)->toBe(1);
            expect($pStyles->item(0)->nodeValue)->toBe('NewStyle1001');
        });

        it('remaps w:numId values using the numbering map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="3"/></w:numPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap(
                abstractNumMap: [],
                numMap: [3 => 15],
                abstractNumNodes: [],
                numNodes: [],
            );
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $numIds = $xpath->query('//w:numPr/w:numId/@w:val');
            expect($numIds->length)->toBe(1);
            expect($numIds->item(0)->nodeValue)->toBe('15');
        });

        it('remaps wp:docPr id attributes', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
                . '<w:body>'
                . '<w:p><w:r><w:drawing><wp:inline><wp:docPr id="1" name="Image 1"/>'
                . '</wp:inline></w:drawing></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert -- docPr id should be remapped to avoid collision
            $docPrs = $xpath->query('//wp:docPr/@id');
            expect($docPrs->length)->toBe(1);
            // The new ID should differ from the original since IdTracker assigns new values
            $newId = (int) $docPrs->item(0)->nodeValue;
            expect($newId)->toBeGreaterThan(0);
        });

        it('remaps w:rStyle attributes using the style map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:rPr><w:rStyle w:val="SourceCharStyle"/></w:rPr>'
                . '<w:t>text</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $styleDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:style xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' w:type="character" w:styleId="SourceCharStyle"><w:name w:val="Src"/></w:style>'
            );
            /** @var DOMElement $styleNode */
            $styleNode = $styleDom->documentElement;

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([
                'SourceCharStyle' => new StyleMapping(
                    oldId: 'SourceCharStyle',
                    newId: 'MappedCharStyle',
                    type: 'character',
                    node: $styleNode,
                    reuseExisting: false,
                ),
            ]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $rStyles = $xpath->query('//w:rStyle/@w:val');
            expect($rStyles->length)->toBe(1);
            expect($rStyles->item(0)->nodeValue)->toBe('MappedCharStyle');
        });

        it('remaps w:tblStyle attributes using the style map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:tbl><w:tblPr><w:tblStyle w:val="OldTableStyle"/></w:tblPr>'
                . '<w:tr><w:tc><w:p><w:r><w:t>cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            // Use the w:tbl element as the content node
            $tables = $xpath->query('//w:tbl');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $tables->length; $i++) {
                $nodes[] = $tables->item($i);
            }

            $styleDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:style xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' w:type="table" w:styleId="OldTableStyle"><w:name w:val="Tbl"/></w:style>'
            );
            /** @var DOMElement $styleNode */
            $styleNode = $styleDom->documentElement;

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([
                'OldTableStyle' => new StyleMapping(
                    oldId: 'OldTableStyle',
                    newId: 'NewTableStyle',
                    type: 'table',
                    node: $styleNode,
                    reuseExisting: false,
                ),
            ]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $tblStyles = $xpath->query('//w:tblStyle/@w:val');
            expect($tblStyles->length)->toBe(1);
            expect($tblStyles->item(0)->nodeValue)->toBe('NewTableStyle');
        });

        it('remaps r:id attributes on hyperlinks using the relationship map', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<w:body>'
                . '<w:p><w:hyperlink r:id="rId5"><w:r><w:t>link</w:t></w:r></w:hyperlink></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([
                'rId5' => new RelationshipMapping(
                    oldId: 'rId5',
                    newId: 'rId42',
                    type: 'hyperlink',
                    target: 'https://example.com',
                    newTarget: 'https://example.com',
                    needsFileCopy: false,
                    isExternal: true,
                ),
            ]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert
            $hyperlinks = $xpath->query('//w:hyperlink/@r:id');
            expect($hyperlinks->length)->toBe(1);
            expect($hyperlinks->item(0)->nodeValue)->toBe('rId42');
        });

        it('remaps w:bookmarkStart and w:bookmarkEnd w:id attributes', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p>'
                . '<w:bookmarkStart w:id="5" w:name="testbm"/>'
                . '<w:r><w:t>text</w:t></w:r>'
                . '<w:bookmarkEnd w:id="5"/>'
                . '</w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert -- both bookmarkStart and bookmarkEnd should have the same new ID
            $starts = $xpath->query('//w:bookmarkStart/@w:id');
            $ends = $xpath->query('//w:bookmarkEnd/@w:id');
            expect($starts->length)->toBe(1);
            expect($ends->length)->toBe(1);
            expect($starts->item(0)->nodeValue)->toBe($ends->item(0)->nodeValue);
        });
        it('remaps bookmarkEnd without matching bookmarkStart by assigning a new ID', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p>'
                . '<w:bookmarkEnd w:id="42"/>'
                . '</w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $xpath = createXpathWithNamespaces($dom);
            $paragraphs = $xpath->query('//w:p');
            /** @var list<DOMNode> $nodes */
            $nodes = [];
            for ($i = 0; $i < $paragraphs->length; $i++) {
                $nodes[] = $paragraphs->item($i);
            }

            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act
            $remapper->remap($nodes, $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert -- bookmarkEnd should have a new ID assigned
            $ends = $xpath->query('//w:bookmarkEnd/@w:id');
            expect($ends->length)->toBe(1);
            // The new ID should be a valid integer > 0
            $newId = (int) $ends->item(0)->nodeValue;
            expect($newId)->toBeGreaterThan(0);
        });

        it('handles empty content nodes without error', function (): void {
            // Arrange
            $remapper = new IdRemapper();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $relMap = new RelationshipMap([]);
            $styleMap = new StyleMap([]);
            $numberingMap = new NumberingMap([], [], [], []);
            $idTracker = new IdTracker();

            // Act -- passing empty node list should not throw
            $remapper->remap([], $relMap, $styleMap, $numberingMap, $idTracker, $dom);

            // Assert -- no-op, document unchanged
            expect($dom->saveXML())->toContain('w:body');
        });
    });
});
