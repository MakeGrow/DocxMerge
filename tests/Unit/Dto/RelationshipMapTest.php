<?php

declare(strict_types=1);

/**
 * Tests for RelationshipMap.
 *
 * Verifies lookup and filtering methods for relationship ID remapping
 * and file copy determination during document merge operations.
 */

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\RelationshipMapping;

describe('RelationshipMap', function (): void {
    describe('getNewId()', function (): void {
        it('returns the mapped new rId when a mapping exists', function (): void {
            // Arrange
            $mapping = new RelationshipMapping(
                oldId: 'rId1',
                newId: 'rId10',
                type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                target: 'media/image1.png',
                newTarget: 'media/image5.png',
                needsFileCopy: true,
                isExternal: false,
            );
            $map = new RelationshipMap(mappings: ['rId1' => $mapping]);

            // Act
            $result = $map->getNewId('rId1');

            // Assert
            expect($result)->toBe('rId10');
        });

        it('returns null when no mapping exists for the given rId', function (): void {
            // Arrange
            $map = new RelationshipMap(mappings: []);

            // Act
            $result = $map->getNewId('rId99');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('getFileTarget()', function (): void {
        it('returns the target path when a mapping exists', function (): void {
            // Arrange
            $mapping = new RelationshipMapping(
                oldId: 'rId2',
                newId: 'rId20',
                type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                target: 'media/image2.jpg',
                newTarget: 'media/image6.jpg',
                needsFileCopy: true,
                isExternal: false,
            );
            $map = new RelationshipMap(mappings: ['rId2' => $mapping]);

            // Act
            $result = $map->getFileTarget('rId2');

            // Assert
            expect($result)->toBe('media/image2.jpg');
        });

        it('returns null when no mapping exists for the given rId', function (): void {
            // Arrange
            $map = new RelationshipMap(mappings: []);

            // Act
            $result = $map->getFileTarget('rId99');

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('getFilesToCopy()', function (): void {
        it('returns only mappings that require file copy', function (): void {
            // Arrange
            $needsCopy = new RelationshipMapping(
                oldId: 'rId1',
                newId: 'rId10',
                type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                target: 'media/image1.png',
                newTarget: 'media/image5.png',
                needsFileCopy: true,
                isExternal: false,
            );
            $noCopy = new RelationshipMapping(
                oldId: 'rId2',
                newId: 'rId11',
                type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
                target: 'https://example.com',
                newTarget: 'https://example.com',
                needsFileCopy: false,
                isExternal: true,
            );
            $map = new RelationshipMap(mappings: [
                'rId1' => $needsCopy,
                'rId2' => $noCopy,
            ]);

            // Act
            $result = $map->getFilesToCopy();

            // Assert
            expect($result)->toHaveCount(1)
                ->and($result)->toHaveKey('rId1')
                ->and($result['rId1']->newTarget)->toBe('media/image5.png');
        });

        it('returns an empty array when no mappings require file copy', function (): void {
            // Arrange
            $external = new RelationshipMapping(
                oldId: 'rId1',
                newId: 'rId10',
                type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
                target: 'https://example.com',
                newTarget: 'https://example.com',
                needsFileCopy: false,
                isExternal: true,
            );
            $map = new RelationshipMap(mappings: ['rId1' => $external]);

            // Act
            $result = $map->getFilesToCopy();

            // Assert
            expect($result)->toBeEmpty();
        });
    });
});
