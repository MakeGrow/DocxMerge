<?php

declare(strict_types=1);

/**
 * Tests for ContentTypesManager.
 *
 * Verifies that [Content_Types].xml is updated with Default entries
 * for file extensions and Override entries for specific parts, without
 * duplicating entries that already exist.
 */

use DocxMerge\ContentTypes\ContentTypesManager;

describe('ContentTypesManager', function (): void {
    // Holds the path of the temporary ZIP created during the test so it
    // can be deleted in afterEach() even when the test fails.
    $tempZipPath = '';

    afterEach(function () use (&$tempZipPath): void {
        if ($tempZipPath !== '' && file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        $tempZipPath = '';
    });

    describe('update()', function (): void {
        it('adds a Default entry for a new image extension', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '</Types>'
            );

            // Create a temporary ZIP with a PNG image entry
            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/media/image1.png', 'fake-png-data');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should have a Default entry for "png"
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $defaults = $xpath->query('//ct:Default[@Extension="png"]');
            expect($defaults->length)->toBe(1);
        });

        it('adds an Override entry for a header part', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '</Types>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/header1.xml', '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should have an Override for /word/header1.xml
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $overrides = $xpath->query('//ct:Override[@PartName="/word/header1.xml"]');
            expect($overrides->length)->toBe(1);
        });

        it('adds an Override entry for a footer part', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '</Types>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/footer1.xml', '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should have an Override for /word/footer1.xml with footer content type
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $overrides = $xpath->query('//ct:Override[@PartName="/word/footer1.xml"]');
            expect($overrides->length)->toBe(1);

            $contentType = $overrides->item(0)->getAttribute('ContentType');
            expect($contentType)->toContain('footer+xml');
        });

        it('does not duplicate an existing Override entry', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
                . '</Types>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/header1.xml', '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should still have exactly one Override for header1.xml
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $overrides = $xpath->query('//ct:Override[@PartName="/word/header1.xml"]');
            expect($overrides->length)->toBe(1);
        });

        it('uses application/octet-stream for unknown media extensions', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '</Types>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            // An unusual extension not in the known list
            $zip->addFromString('word/media/image1.webp', 'fake-webp-data');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should use application/octet-stream for unknown extension
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $defaults = $xpath->query('//ct:Default[@Extension="webp"]');
            expect($defaults->length)->toBe(1);
            expect($defaults->item(0)->getAttribute('ContentType'))->toBe('application/octet-stream');
        });

        it('skips ZIP entries outside word/media/', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            // File not in word/media/ should be ignored
            $zip->addFromString('word/document.xml', '<w:document/>');
            // File not matching header/footer pattern
            $zip->addFromString('word/settings.xml', '<w:settings/>');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- no Default or Override entries should be added
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $defaults = $xpath->query('//ct:Default');
            $overrides = $xpath->query('//ct:Override');
            expect($defaults->length)->toBe(0);
            expect($overrides->length)->toBe(0);
        });

        it('does not duplicate an existing Default entry', function () use (&$tempZipPath): void {
            // Arrange
            $manager = new ContentTypesManager();
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="png" ContentType="image/png"/>'
                . '</Types>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'ct_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/media/image1.png', 'fake-png-data');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $manager->update($contentTypesDom, $zip);
            $zip->close();

            // Assert -- should still have exactly one Default for "png"
            $xpath = createXpathWithNamespaces($contentTypesDom);
            $defaults = $xpath->query('//ct:Default[@Extension="png"]');
            expect($defaults->length)->toBe(1);
        });
    });
});
