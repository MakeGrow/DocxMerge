<?php

declare(strict_types=1);

/**
 * Tests for StyleMerger.
 *
 * Verifies style conflict detection using content hash comparison
 * and correct import of new and renamed styles into the target.
 */

use DocxMerge\Dto\StyleMap;
use DocxMerge\Style\StyleMerger;

describe('StyleMerger', function (): void {
    describe('buildMap()', function (): void {
        it('maps a new style that does not exist in the target', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="CustomStyle">'
                . '<w:name w:val="Custom Style"/>'
                . '<w:pPr><w:spacing w:after="200"/></w:pPr>'
                . '</w:style>'
                . '</w:styles>'
            );

            // Act
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Assert
            expect($map)->toBeInstanceOf(StyleMap::class);
            expect($map->hasMapping('CustomStyle'))->toBeTrue();
            expect($map->getNewId('CustomStyle'))->toBe('CustomStyle');
            expect($map->isReused('CustomStyle'))->toBeFalse();
        });

        it('marks identical style as reuse_existing', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $styleXml = '<w:style w:type="paragraph" w:styleId="Normal">'
                . '<w:name w:val="Normal"/>'
                . '<w:pPr><w:spacing w:after="160"/></w:pPr>'
                . '</w:style>';
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . $styleXml
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . $styleXml
                . '</w:styles>'
            );

            // Act
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Assert
            expect($map->hasMapping('Normal'))->toBeTrue();
            expect($map->isReused('Normal'))->toBeTrue();
        });

        it('renames a conflicting style with a different definition', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Heading1">'
                . '<w:name w:val="heading 1"/>'
                . '<w:pPr><w:spacing w:after="100"/></w:pPr>'
                . '</w:style>'
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Heading1">'
                . '<w:name w:val="heading 1"/>'
                . '<w:pPr><w:spacing w:after="999"/></w:pPr>'
                . '</w:style>'
                . '</w:styles>'
            );

            // Act
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Assert
            expect($map->hasMapping('Heading1'))->toBeTrue();
            expect($map->getNewId('Heading1'))->not->toBe('Heading1');
            expect($map->isReused('Heading1'))->toBeFalse();
        });
        it('produces consistent IDs when buildMap is called multiple times on the same instance', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Heading1">'
                . '<w:name w:val="heading 1"/>'
                . '<w:pPr><w:spacing w:after="100"/></w:pPr>'
                . '</w:style>'
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Heading1">'
                . '<w:name w:val="heading 1"/>'
                . '<w:pPr><w:spacing w:after="999"/></w:pPr>'
                . '</w:style>'
                . '</w:styles>'
            );

            // Act -- call buildMap twice on the same instance
            $map1 = $merger->buildMap($sourceDom, $targetDom);
            $map2 = $merger->buildMap($sourceDom, $targetDom);

            // Assert -- both calls should produce the same ID (Style1000)
            // because the counter should reset to 0 on each call.
            expect($map1->getNewId('Heading1'))->toBe($map2->getNewId('Heading1'));
        });

        it('returns an empty map when source has no styles', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );

            // Act
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Assert
            expect($map->mappings)->toBe([]);
        });
    });

    describe('merge()', function (): void {
        it('imports a new style into the target DOM', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="NewStyle">'
                . '<w:name w:val="New Style"/>'
                . '</w:style>'
                . '</w:styles>'
            );
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Act
            $imported = $merger->merge($targetDom, $map);

            // Assert
            expect($imported)->toBe(1);
            $xpath = createXpathWithNamespaces($targetDom);
            $styles = $xpath->query('//w:style[@w:styleId="NewStyle"]');
            expect($styles->length)->toBe(1);
        });

        it('does not import a reused style', function (): void {
            // Arrange
            $merger = new StyleMerger();
            $sharedXml = '<w:style w:type="paragraph" w:styleId="Normal">'
                . '<w:name w:val="Normal"/></w:style>';
            $targetDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . $sharedXml . '</w:styles>'
            );
            $sourceDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . $sharedXml . '</w:styles>'
            );
            $map = $merger->buildMap($sourceDom, $targetDom);

            // Act
            $imported = $merger->merge($targetDom, $map);

            // Assert
            expect($imported)->toBe(0);
        });
    });
});
