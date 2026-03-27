<?php

declare(strict_types=1);

/**
 * Tests for XmlHelper.
 *
 * Verifies DOM creation with safe defaults, XPath namespace registration,
 * and whitespace preservation on w:t elements.
 */

use DocxMerge\Exception\XmlParseException;
use DocxMerge\Xml\XmlHelper;

describe('XmlHelper', function (): void {
    describe('createDom()', function (): void {
        it('creates a DOMDocument from valid XML', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0" encoding="UTF-8"?><root><child>text</child></root>';

            // Act
            $dom = $helper->createDom($xml);

            // Assert
            expect($dom)->toBeInstanceOf(DOMDocument::class);
            expect($dom->documentElement)->not->toBeNull();
            expect($dom->documentElement->nodeName)->toBe('root');
        });

        it('throws XmlParseException for malformed XML', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<root><unclosed>';

            // Act + Assert
            expect(fn () => $helper->createDom($xml))
                ->toThrow(XmlParseException::class);
        });

        it('configures preserveWhiteSpace as true', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?><root> text with spaces </root>';

            // Act
            $dom = $helper->createDom($xml);

            // Assert
            expect($dom->preserveWhiteSpace)->toBeTrue();
        });

        it('configures formatOutput as false', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?><root><child/></root>';

            // Act
            $dom = $helper->createDom($xml);

            // Assert
            expect($dom->formatOutput)->toBeFalse();
        });
    });

    describe('createXpath()', function (): void {
        it('registers the WordprocessingML namespace', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:p><w:r><w:t>Hello</w:t></w:r></w:p></w:body>'
                . '</w:document>';
            $dom = $helper->createDom($xml);

            // Act
            $xpath = $helper->createXpath($dom);

            // Assert
            $nodes = $xpath->query('//w:t');
            expect($nodes)->not->toBeFalse();
            expect($nodes->length)->toBe(1);
        });

        it('registers the relationships namespace', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="test" Target="test.xml"/>'
                . '</Relationships>';
            $dom = $helper->createDom($xml);

            // Act
            $xpath = $helper->createXpath($dom);

            // Assert
            $nodes = $xpath->query('//rel:Relationship');
            expect($nodes)->not->toBeFalse();
            expect($nodes->length)->toBe(1);
        });
    });

    describe('preserveTextSpaces()', function (): void {
        it('adds xml:space preserve on w:t with leading space', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:p><w:r><w:t> leading</w:t></w:r></w:p></w:body>'
                . '</w:document>';
            $dom = $helper->createDom($xml);

            // Act
            $helper->preserveTextSpaces($dom);

            // Assert
            $xpath = $helper->createXpath($dom);
            $textNodes = $xpath->query('//w:t');
            expect($textNodes->length)->toBe(1);
            /** @var DOMElement $textNode */
            $textNode = $textNodes->item(0);
            expect($textNode->getAttribute('xml:space'))->toBe('preserve');
        });

        it('adds xml:space preserve on w:t with trailing space', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:p><w:r><w:t>trailing </w:t></w:r></w:p></w:body>'
                . '</w:document>';
            $dom = $helper->createDom($xml);

            // Act
            $helper->preserveTextSpaces($dom);

            // Assert
            $xpath = $helper->createXpath($dom);
            /** @var DOMElement $textNode */
            $textNode = $xpath->query('//w:t')->item(0);
            expect($textNode->getAttribute('xml:space'))->toBe('preserve');
        });

        it('does not add xml:space on w:t without surrounding spaces', function (): void {
            // Arrange
            $helper = new XmlHelper();
            $xml = '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:p><w:r><w:t>nospaces</w:t></w:r></w:p></w:body>'
                . '</w:document>';
            $dom = $helper->createDom($xml);

            // Act
            $helper->preserveTextSpaces($dom);

            // Assert
            $xpath = $helper->createXpath($dom);
            /** @var DOMElement $textNode */
            $textNode = $xpath->query('//w:t')->item(0);
            expect($textNode->hasAttribute('xml:space'))->toBeFalse();
        });
    });
});
