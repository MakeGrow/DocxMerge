<?php

declare(strict_types=1);

/**
 * Tests for ContentExtractor.
 *
 * Verifies content extraction from source documents with single and
 * multiple sections, including section property preservation.
 */

use DocxMerge\Content\ContentExtractor;
use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Exception\InvalidSourceException;

describe('ContentExtractor', function (): void {
    describe('extract()', function (): void {
        it('extracts all paragraphs from a single-section document', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Paragraph 1</w:t></w:r></w:p>'
                . '<w:p><w:r><w:t>Paragraph 2</w:t></w:r></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert
            expect($result)->toBeInstanceOf(ExtractedContent::class);
            expect($result->nodes)->toHaveCount(2);
            expect($result->sectionCount)->toBe(1);
        });

        it('excludes the final sectPr from extracted nodes', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Content</w:t></w:r></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert
            expect($result->finalSectPr)->not->toBeNull();
            expect($result->finalSectPr->nodeName)->toBe('w:sectPr');
            // Nodes should not contain the sectPr
            foreach ($result->nodes as $node) {
                expect($node->nodeName)->not->toBe('w:sectPr');
            }
        });

        it('preserves intermediate sectPr elements in multi-section documents', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Section 1 content</w:t></w:r></w:p>'
                . '<w:p><w:pPr><w:sectPr>'
                . '<w:headerReference w:type="default" r:id="rId4"/>'
                . '</w:sectPr></w:pPr></w:p>'
                . '<w:p><w:r><w:t>Section 2 content</w:t></w:r></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert
            expect($result->sectionCount)->toBe(2);
            expect($result->intermediateSectPrElements)->toHaveCount(1);
        });

        it('extracts content for a specific section index', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Section 0</w:t></w:r></w:p>'
                . '<w:p><w:pPr><w:sectPr/></w:pPr></w:p>'
                . '<w:p><w:r><w:t>Section 1</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom, sectionIndex: 1);

            // Assert
            expect($result->nodes)->toHaveCount(1);
        });

        it('handles a document without a final sectPr gracefully', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Paragraph 1</w:t></w:r></w:p>'
                . '<w:p><w:r><w:t>Paragraph 2</w:t></w:r></w:p>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert -- both paragraphs should be extracted, finalSectPr should be null
            expect($result->nodes)->toHaveCount(2);
            expect($result->finalSectPr)->toBeNull();
            expect($result->sectionCount)->toBe(1);
        });

        it('returns empty nodes for a body with only a sectPr', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert -- no content paragraphs, just the final sectPr
            expect($result->nodes)->toHaveCount(0);
            expect($result->finalSectPr)->not->toBeNull();
            expect($result->sectionCount)->toBe(1);
        });

        it('throws InvalidSourceException for negative section index', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Content</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act + Assert
            expect(fn () => $extractor->extract($dom, sectionIndex: -1))
                ->toThrow(InvalidSourceException::class);
        });

        it('filters out whitespace text nodes from body children', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            // XML com newlines entre elementos gera text nodes quando preserveWhiteSpace=true
            $xml = '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . "<w:body>\n"
                . "  <w:p><w:r><w:t>Para 1</w:t></w:r></w:p>\n"
                . "  <w:p><w:r><w:t>Para 2</w:t></w:r></w:p>\n"
                . "  <w:sectPr/>\n"
                . '</w:body></w:document>';
            $dom = createDomFromXml($xml);

            // Act
            $result = $extractor->extract($dom);

            // Assert -- only DOMElement nodes, no text nodes
            expect($result->nodes)->toHaveCount(2);
            foreach ($result->nodes as $node) {
                expect($node)->toBeInstanceOf(DOMElement::class);
            }
        });

        it('ignores non-paragraph elements when scanning for intermediate sectPr', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
                . '<w:p><w:pPr><w:sectPr/></w:pPr></w:p>'
                . '<w:p><w:r><w:t>Section 2</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $extractor->extract($dom);

            // Assert
            expect($result->sectionCount)->toBe(2);
            expect($result->intermediateSectPrElements)->toHaveCount(1);
            // All 3 content nodes (table + 2 paragraphs) should be extracted
            expect($result->nodes)->toHaveCount(3);
        });

        it('throws InvalidSourceException for out-of-bounds section index', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Only one section</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act + Assert
            expect(fn () => $extractor->extract($dom, sectionIndex: 5))
                ->toThrow(InvalidSourceException::class);
        });
    });

    describe('countSections()', function (): void {
        it('returns 1 for a single-section document', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Content</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $count = $extractor->countSections($dom);

            // Assert
            expect($count)->toBe(1);
        });

        it('returns 3 for a three-section document', function (): void {
            // Arrange
            $extractor = new ContentExtractor();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:sectPr/></w:pPr></w:p>'
                . '<w:p><w:pPr><w:sectPr/></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $count = $extractor->countSections($dom);

            // Assert
            expect($count)->toBe(3);
        });
    });
});
