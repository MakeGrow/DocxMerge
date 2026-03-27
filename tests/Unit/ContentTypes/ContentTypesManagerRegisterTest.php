<?php

declare(strict_types=1);

/**
 * Tests for ContentTypesManager::registerRequiredPart().
 *
 * Verifies that Override and Relationship entries are correctly added
 * to in-memory DOMs when registering a required OOXML part, with
 * idempotency checks for duplicate prevention.
 */

use DocxMerge\ContentTypes\ContentTypesManager;
use DocxMerge\Tracking\IdTracker;

describe('ContentTypesManager::registerRequiredPart()', function (): void {
    it('registers a required part with override and relationship', function (): void {
        // Arrange
        $manager = new ContentTypesManager();
        $contentTypesDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '</Types>'
        );
        $relsDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>'
        );
        $idTracker = new IdTracker();

        // Act
        $manager->registerRequiredPart(
            $contentTypesDom,
            $relsDom,
            '/word/numbering.xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering',
            'numbering.xml',
            $idTracker,
        );

        // Assert -- Override added
        $ctXpath = createXpathWithNamespaces($contentTypesDom);
        $overrides = $ctXpath->query('//ct:Override[@PartName="/word/numbering.xml"]');
        assert($overrides !== false);
        expect($overrides->length)->toBe(1);
        $overrideNode = $overrides->item(0);
        assert($overrideNode instanceof DOMElement);
        expect($overrideNode->getAttribute('ContentType'))
            ->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml');

        // Assert -- Relationship added
        $relXpath = createXpathWithNamespaces($relsDom);
        $rels = $relXpath->query('//rel:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering"]');
        assert($rels !== false);
        expect($rels->length)->toBe(1);
        $relNode = $rels->item(0);
        assert($relNode instanceof DOMElement);
        expect($relNode->getAttribute('Target'))->toBe('numbering.xml');
        expect($relNode->getAttribute('Id'))->not->toBeEmpty();
    });

    it('does not duplicate override when already present', function (): void {
        // Arrange
        $manager = new ContentTypesManager();
        $contentTypesDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '</Types>'
        );
        $relsDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
        );
        $idTracker = new IdTracker();

        // Act
        $manager->registerRequiredPart(
            $contentTypesDom,
            $relsDom,
            '/word/numbering.xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering',
            'numbering.xml',
            $idTracker,
        );

        // Assert -- still exactly one Override
        $ctXpath = createXpathWithNamespaces($contentTypesDom);
        $overrides = $ctXpath->query('//ct:Override[@PartName="/word/numbering.xml"]');
        assert($overrides !== false);
        expect($overrides->length)->toBe(1);
    });

    it('does not duplicate relationship when already present', function (): void {
        // Arrange
        $manager = new ContentTypesManager();
        $contentTypesDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
        );
        $relsDom = createDomFromXml(
            '<?xml version="1.0"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            . '</Relationships>'
        );
        $idTracker = new IdTracker();

        // Act
        $manager->registerRequiredPart(
            $contentTypesDom,
            $relsDom,
            '/word/numbering.xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering',
            'numbering.xml',
            $idTracker,
        );

        // Assert -- still exactly one Relationship with numbering type
        $relXpath = createXpathWithNamespaces($relsDom);
        $rels = $relXpath->query('//rel:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering"]');
        assert($rels !== false);
        expect($rels->length)->toBe(1);
    });
});
