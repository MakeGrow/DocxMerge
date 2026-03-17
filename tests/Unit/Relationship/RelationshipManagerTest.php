<?php

declare(strict_types=1);

/**
 * Tests for RelationshipManager.
 *
 * Verifies relationship mapping between source and target documents,
 * including filtering by content reference, exclusion of structural
 * and header/footer relationships, and duplicate detection.
 */

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Relationship\RelationshipManager;
use DocxMerge\Tracking\IdTracker;

describe('RelationshipManager', function (): void {
    describe('buildMap()', function (): void {
        it('maps an image relationship referenced in the content', function (): void {
            // Arrange
            $manager = new RelationshipManager();
            $sourceRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="media/image1.png"/>'
                . '</Relationships>'
            );
            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            // Content references rId1
            $contentXml = '<w:drawing xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<a:blip r:embed="rId1"/></w:drawing>';
            $idTracker = new IdTracker();

            // Act
            $map = $manager->buildMap($sourceRelsDom, $targetRelsDom, $contentXml, $idTracker);

            // Assert
            expect($map)->toBeInstanceOf(RelationshipMap::class);
            expect($map->getNewId('rId1'))->not->toBeNull();
        });

        it('excludes structural relationships like styles and settings', function (): void {
            // Arrange
            $manager = new RelationshipManager();
            $sourceRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
                . ' Target="styles.xml"/>'
                . '<Relationship Id="rId2"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings"'
                . ' Target="settings.xml"/>'
                . '<Relationship Id="rId3"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable"'
                . ' Target="fontTable.xml"/>'
                . '</Relationships>'
            );
            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            // Content references all three IDs
            $contentXml = '<w:r xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<w:t>rId1 rId2 rId3</w:t></w:r>';
            $idTracker = new IdTracker();

            // Act
            $map = $manager->buildMap($sourceRelsDom, $targetRelsDom, $contentXml, $idTracker);

            // Assert -- structural rels should not be mapped
            expect($map->getNewId('rId1'))->toBeNull();
            expect($map->getNewId('rId2'))->toBeNull();
            expect($map->getNewId('rId3'))->toBeNull();
        });

        it('excludes header and footer relationships', function (): void {
            // Arrange
            $manager = new RelationshipManager();
            $sourceRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header"'
                . ' Target="header1.xml"/>'
                . '<Relationship Id="rId2"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer"'
                . ' Target="footer1.xml"/>'
                . '</Relationships>'
            );
            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentXml = '<w:r xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:t>rId1 rId2</w:t></w:r>';
            $idTracker = new IdTracker();

            // Act
            $map = $manager->buildMap($sourceRelsDom, $targetRelsDom, $contentXml, $idTracker);

            // Assert -- header/footer rels handled by HeaderFooterCopier
            expect($map->getNewId('rId1'))->toBeNull();
            expect($map->getNewId('rId2'))->toBeNull();
        });

        it('excludes relationships not referenced in the content', function (): void {
            // Arrange
            $manager = new RelationshipManager();
            $sourceRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="media/image1.png"/>'
                . '<Relationship Id="rId2"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="media/image2.png"/>'
                . '</Relationships>'
            );
            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            // Content only references rId1, not rId2
            $contentXml = '<w:drawing xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<a:blip r:embed="rId1"/></w:drawing>';
            $idTracker = new IdTracker();

            // Act
            $map = $manager->buildMap($sourceRelsDom, $targetRelsDom, $contentXml, $idTracker);

            // Assert
            expect($map->getNewId('rId1'))->not->toBeNull();
            expect($map->getNewId('rId2'))->toBeNull();
        });
    });

    describe('addRelationships()', function (): void {
        it('adds new relationship elements to the target rels DOM', function (): void {
            // Arrange
            $manager = new RelationshipManager();
            $sourceRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="media/image1.png"/>'
                . '</Relationships>'
            );
            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentXml = '<a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
                . ' r:embed="rId1"/>';
            $idTracker = new IdTracker();
            $map = $manager->buildMap($sourceRelsDom, $targetRelsDom, $contentXml, $idTracker);

            // Act
            $manager->addRelationships($targetRelsDom, $map);

            // Assert -- target should now contain a Relationship element
            $xpath = createXpathWithNamespaces($targetRelsDom);
            $rels = $xpath->query('//rel:Relationship');
            expect($rels->length)->toBeGreaterThan(0);
        });
    });
});
