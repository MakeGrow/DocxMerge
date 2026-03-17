<?php

declare(strict_types=1);

/**
 * Tests for NumberingMap.
 *
 * Verifies lookup methods for numId and abstractNumId mappings used
 * during numbering remapping in merged documents.
 */

use DocxMerge\Dto\NumberingMap;

describe('NumberingMap', function (): void {
    describe('getNewNumId()', function (): void {
        it('returns the mapped new numId when a mapping exists', function (): void {
            // Arrange
            $map = new NumberingMap(
                abstractNumMap: [],
                numMap: [1 => 1001, 2 => 1002],
                abstractNumNodes: [],
                numNodes: [],
            );

            // Act
            $result = $map->getNewNumId(1);

            // Assert
            expect($result)->toBe(1001);
        });

        it('returns null when no mapping exists for the given numId', function (): void {
            // Arrange
            $map = new NumberingMap(
                abstractNumMap: [],
                numMap: [],
                abstractNumNodes: [],
                numNodes: [],
            );

            // Act
            $result = $map->getNewNumId(999);

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('getNewAbstractNumId()', function (): void {
        it('returns the mapped new abstractNumId when a mapping exists', function (): void {
            // Arrange
            $map = new NumberingMap(
                abstractNumMap: [5 => 50005],
                numMap: [],
                abstractNumNodes: [],
                numNodes: [],
            );

            // Act
            $result = $map->getNewAbstractNumId(5);

            // Assert
            expect($result)->toBe(50005);
        });

        it('returns null when no mapping exists for the given abstractNumId', function (): void {
            // Arrange
            $map = new NumberingMap(
                abstractNumMap: [],
                numMap: [],
                abstractNumNodes: [],
                numNodes: [],
            );

            // Act
            $result = $map->getNewAbstractNumId(42);

            // Assert
            expect($result)->toBeNull();
        });
    });
});
