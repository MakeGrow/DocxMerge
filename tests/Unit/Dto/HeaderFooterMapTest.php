<?php

declare(strict_types=1);

/**
 * Tests for HeaderFooterMap.
 *
 * Verifies lookup methods for header/footer relationship ID and filename
 * remapping used during section properties update in merged documents.
 */

use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Dto\HeaderFooterMapping;

describe('HeaderFooterMap', function (): void {
    describe('getNewRelId()', function (): void {
        it('returns the mapped new relationship ID when a mapping exists', function (): void {
            // Arrange
            $mapping = new HeaderFooterMapping(
                oldId: 'rId6',
                newRelId: 'rId20',
                oldTarget: 'header1.xml',
                newFilename: 'header3.xml',
                type: 'default',
                isHeader: true,
            );
            $map = new HeaderFooterMap(mappings: ['rId6' => $mapping]);

            // Act
            $result = $map->getNewRelId('rId6');

            // Assert
            expect($result)->toBe('rId20');
        });

        it('returns null when no mapping exists for the given rId', function (): void {
            // Arrange
            $map = new HeaderFooterMap(mappings: []);

            // Act
            $result = $map->getNewRelId('rId99');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('getNewFilename()', function (): void {
        it('returns the mapped new filename when a mapping exists', function (): void {
            // Arrange
            $mapping = new HeaderFooterMapping(
                oldId: 'rId7',
                newRelId: 'rId21',
                oldTarget: 'footer1.xml',
                newFilename: 'footer3.xml',
                type: 'default',
                isHeader: false,
            );
            $map = new HeaderFooterMap(mappings: ['rId7' => $mapping]);

            // Act
            $result = $map->getNewFilename('rId7');

            // Assert
            expect($result)->toBe('footer3.xml');
        });

        it('returns null when no mapping exists for the given rId', function (): void {
            // Arrange
            $map = new HeaderFooterMap(mappings: []);

            // Act
            $result = $map->getNewFilename('rId99');

            // Assert
            expect($result)->toBeNull();
        });
    });
});
