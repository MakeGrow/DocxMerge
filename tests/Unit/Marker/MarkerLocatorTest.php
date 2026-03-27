<?php

declare(strict_types=1);

/**
 * Tests for MarkerLocator.
 *
 * Verifies marker detection in template documents, including markers
 * split across multiple w:r/w:t elements by Word's XML serialization.
 */

use DocxMerge\Dto\MarkerLocation;
use DocxMerge\Marker\MarkerLocator;

describe('MarkerLocator', function (): void {
    describe('locate()', function (): void {
        it('finds a marker in a single w:t element', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>${CONTENT}</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'CONTENT', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeInstanceOf(MarkerLocation::class);
            expect($result->paragraph->nodeName)->toBe('w:p');
        });

        it('finds a marker split across multiple w:t elements', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p>'
                . '<w:r><w:t>$</w:t></w:r>'
                . '<w:r><w:t>{CONTENT</w:t></w:r>'
                . '<w:r><w:t>}</w:t></w:r>'
                . '</w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'CONTENT', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeInstanceOf(MarkerLocation::class);
            expect($result->paragraph->nodeName)->toBe('w:p');
        });

        it('returns null when marker is not found', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Regular text without markers</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'MISSING', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeNull();
        });

        it('finds a marker inside a table cell', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:tbl><w:tr><w:tc>'
                . '<w:p><w:r><w:t>${TABLE_MARKER}</w:t></w:r></w:p>'
                . '</w:tc></w:tr></w:tbl>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'TABLE_MARKER', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeInstanceOf(MarkerLocation::class);
        });

        it('returns null for a document with no paragraphs', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'CONTENT', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeNull();
        });

        it('returns null for a paragraph with no w:t elements', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:jc w:val="center"/></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'CONTENT', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeNull();
        });

        it('finds the correct marker when multiple markers exist', function (): void {
            // Arrange
            $locator = new MarkerLocator();
            $dom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:r><w:t>${FIRST}</w:t></w:r></w:p>'
                . '<w:p><w:r><w:t>${SECOND}</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );

            // Act
            $result = $locator->locate($dom, 'SECOND', '/\$\{([A-Z_][A-Z0-9_]*)\}/');

            // Assert
            expect($result)->toBeInstanceOf(MarkerLocation::class);
            // The paragraph should contain the SECOND marker text
            expect($result->paragraph->textContent)->toContain('SECOND');
        });
    });
});
