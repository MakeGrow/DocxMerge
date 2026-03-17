<?php

declare(strict_types=1);

/**
 * Tests for NumberingResequencer.
 *
 * Verifies that abstractNumId and numId values are renumbered sequentially,
 * DOM nodes are reordered (abstractNum before num), and all w:numId
 * references in the document DOM are updated accordingly.
 */

use DocxMerge\Numbering\NumberingResequencer;

describe('NumberingResequencer', function (): void {
    describe('resequence()', function (): void {
        it('renumbers abstractNumId values sequentially starting from 0', function (): void {
            // Arrange
            $resequencer = new NumberingResequencer();
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="5">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:abstractNum w:abstractNumId="12">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="3"><w:abstractNumId w:val="5"/></w:num>'
                . '<w:num w:numId="8"><w:abstractNumId w:val="12"/></w:num>'
                . '</w:numbering>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="3"/></w:numPr></w:pPr></w:p>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="8"/></w:numPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $resequencer->resequence($numberingDom, $documentDom);

            // Assert -- abstractNumIds should be 0, 1
            $xpath = createXpathWithNamespaces($numberingDom);
            $abstractNums = $xpath->query('//w:abstractNum');
            expect($abstractNums->length)->toBe(2);
            /** @var DOMElement $first */
            $first = $abstractNums->item(0);
            /** @var DOMElement $second */
            $second = $abstractNums->item(1);
            expect($first->getAttribute('w:abstractNumId'))->toBe('0');
            expect($second->getAttribute('w:abstractNumId'))->toBe('1');
        });

        it('renumbers numId values sequentially starting from 1', function (): void {
            // Arrange
            $resequencer = new NumberingResequencer();
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="5"><w:abstractNumId w:val="0"/></w:num>'
                . '<w:num w:numId="9"><w:abstractNumId w:val="0"/></w:num>'
                . '</w:numbering>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="5"/></w:numPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $resequencer->resequence($numberingDom, $documentDom);

            // Assert -- numIds should be 1, 2
            $xpath = createXpathWithNamespaces($numberingDom);
            $nums = $xpath->query('//w:num');
            expect($nums->length)->toBe(2);
            /** @var DOMElement $first */
            $first = $nums->item(0);
            /** @var DOMElement $second */
            $second = $nums->item(1);
            expect($first->getAttribute('w:numId'))->toBe('1');
            expect($second->getAttribute('w:numId'))->toBe('2');
        });

        it('reorders DOM nodes so all abstractNum precede all num', function (): void {
            // Arrange -- intentionally interleaved order
            $resequencer = new NumberingResequencer();
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '</w:numbering>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );

            // Act
            $resequencer->resequence($numberingDom, $documentDom);

            // Assert -- abstractNum must come before num
            $xpath = createXpathWithNamespaces($numberingDom);
            $children = $xpath->query('/w:numbering/*');
            expect($children->length)->toBe(2);
            /** @var DOMElement $firstChild */
            $firstChild = $children->item(0);
            /** @var DOMElement $secondChild */
            $secondChild = $children->item(1);
            expect($firstChild->localName)->toBe('abstractNum');
            expect($secondChild->localName)->toBe('num');
        });

        it('updates numId references in the document DOM', function (): void {
            // Arrange
            $resequencer = new NumberingResequencer();
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0">'
                . '<w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl>'
                . '</w:abstractNum>'
                . '<w:num w:numId="7"><w:abstractNumId w:val="0"/></w:num>'
                . '</w:numbering>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $resequencer->resequence($numberingDom, $documentDom);

            // Assert -- numId in document should be updated to "1"
            $xpath = createXpathWithNamespaces($documentDom);
            $numIdNodes = $xpath->query('//w:numPr/w:numId');
            expect($numIdNodes->length)->toBe(1);
            /** @var DOMElement $numIdNode */
            $numIdNode = $numIdNodes->item(0);
            expect($numIdNode->getAttribute('w:val'))->toBe('1');
        });
    });
});
