<?php

declare(strict_types=1);

/**
 * Integration tests for IdTracker.
 *
 * Tests that require real ZIP archives on disk to verify ID counter
 * initialization from target ZIP media and header/footer files.
 */

use DocxMerge\Tracking\IdTracker;

describe('IdTracker (Integration)', function (): void {
    /** @var string $tempZipPath */
    $tempZipPath = '';

    afterEach(function () use (&$tempZipPath): void {
        /** @var string $tempZipPath */
        if ($tempZipPath !== '' && file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        $tempZipPath = '';
    });

    describe('initializeFromTarget()', function () use (&$tempZipPath): void {
        it('detects max image number from ZIP media files', function () use (&$tempZipPath): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'tracker_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/media/image1.png', 'fake');
            $zip->addFromString('word/media/image3.png', 'fake');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);
            $zip->close();

            // Assert
            expect($tracker->nextImageNumber())->toBe(4);
        });

        it('detects max header/footer number from ZIP', function () use (&$tempZipPath): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'tracker_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/header1.xml', '<h/>');
            $zip->addFromString('word/footer2.xml', '<f/>');
            $zip->addFromString('word/header4.xml', '<h/>');
            $zip->close();
            $zip->open($tempZipPath);

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);
            $zip->close();

            // Assert
            expect($tracker->nextHeaderFooterNumber())->toBe(5);
        });
    });
});
