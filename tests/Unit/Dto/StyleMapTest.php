<?php

declare(strict_types=1);

/**
 * Tests for StyleMap.
 *
 * Verifies lookup and filtering methods that resolve style ID mappings
 * and determine which styles need to be imported into the target document.
 */

use DocxMerge\Dto\StyleMap;
use DocxMerge\Dto\StyleMapping;

/**
 * Creates a minimal DOMElement for use in StyleMapping construction.
 *
 * @param string $tagName The element tag name.
 *
 * @return DOMElement A detached DOM element.
 */
function createDomElement(string $tagName = 'w:style'): DOMElement
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $element = $doc->createElement($tagName);
    $doc->appendChild($element);

    /** @var DOMElement $element */
    return $element;
}

describe('StyleMap', function (): void {
    describe('getNewId()', function (): void {
        it('returns the mapped new ID when a mapping exists', function (): void {
            // Arrange
            $mapping = new StyleMapping(
                oldId: 'Heading1',
                newId: 'Heading1_1',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: false,
            );
            $map = new StyleMap(mappings: ['Heading1' => $mapping]);

            // Act
            $result = $map->getNewId('Heading1');

            // Assert
            expect($result)->toBe('Heading1_1');
        });

        it('returns the original ID when no mapping exists', function (): void {
            // Arrange
            $map = new StyleMap(mappings: []);

            // Act
            $result = $map->getNewId('UnmappedStyle');

            // Assert
            expect($result)->toBe('UnmappedStyle');
        });
    });

    describe('hasMapping()', function (): void {
        it('returns true when a mapping exists for the given ID', function (): void {
            // Arrange
            $mapping = new StyleMapping(
                oldId: 'Normal',
                newId: 'Normal',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: true,
            );
            $map = new StyleMap(mappings: ['Normal' => $mapping]);

            // Act + Assert
            expect($map->hasMapping('Normal'))->toBeTrue();
        });

        it('returns false when no mapping exists for the given ID', function (): void {
            // Arrange
            $map = new StyleMap(mappings: []);

            // Act + Assert
            expect($map->hasMapping('Missing'))->toBeFalse();
        });
    });

    describe('isReused()', function (): void {
        it('returns true when the style is reused from the target', function (): void {
            // Arrange
            $mapping = new StyleMapping(
                oldId: 'Normal',
                newId: 'Normal',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: true,
            );
            $map = new StyleMap(mappings: ['Normal' => $mapping]);

            // Act + Assert
            expect($map->isReused('Normal'))->toBeTrue();
        });

        it('returns false when the style needs to be imported', function (): void {
            // Arrange
            $mapping = new StyleMapping(
                oldId: 'CustomStyle',
                newId: 'CustomStyle_1',
                type: 'character',
                node: createDomElement(),
                reuseExisting: false,
            );
            $map = new StyleMap(mappings: ['CustomStyle' => $mapping]);

            // Act + Assert
            expect($map->isReused('CustomStyle'))->toBeFalse();
        });

        it('returns false when no mapping exists for the given ID', function (): void {
            // Arrange
            $map = new StyleMap(mappings: []);

            // Act + Assert
            expect($map->isReused('NonExistent'))->toBeFalse();
        });
    });

    describe('getStylesToImport()', function (): void {
        it('returns only mappings where reuseExisting is false', function (): void {
            // Arrange
            $reused = new StyleMapping(
                oldId: 'Normal',
                newId: 'Normal',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: true,
            );
            $imported = new StyleMapping(
                oldId: 'Custom',
                newId: 'Custom_1',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: false,
            );
            $map = new StyleMap(mappings: [
                'Normal' => $reused,
                'Custom' => $imported,
            ]);

            // Act
            $result = $map->getStylesToImport();

            // Assert
            expect($result)->toHaveCount(1)
                ->and($result)->toHaveKey('Custom')
                ->and($result['Custom']->newId)->toBe('Custom_1');
        });

        it('returns an empty array when all styles are reused', function (): void {
            // Arrange
            $reused = new StyleMapping(
                oldId: 'Normal',
                newId: 'Normal',
                type: 'paragraph',
                node: createDomElement(),
                reuseExisting: true,
            );
            $map = new StyleMap(mappings: ['Normal' => $reused]);

            // Act
            $result = $map->getStylesToImport();

            // Assert
            expect($result)->toBeEmpty();
        });
    });
});
