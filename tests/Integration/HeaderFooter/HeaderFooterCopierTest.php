<?php

declare(strict_types=1);

/**
 * Tests for HeaderFooterCopier.
 *
 * Verifies that headers and footers are copied from source to target ZIP,
 * including local rels for images, and that new relationship IDs are
 * generated and registered correctly.
 */

use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Exception\InvalidSourceException;
use DocxMerge\HeaderFooter\HeaderFooterCopier;
use DocxMerge\Tracking\IdTracker;

describe('HeaderFooterCopier', function (): void {
    /** @var list<string> $tempFiles */
    $tempFiles = [];

    afterEach(function () use (&$tempFiles): void {
        /** @var list<string> $tempFiles */
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $tempFiles = [];
    });

    describe('copy()', function () use (&$tempFiles): void {
        it('copies a header file from source to target and returns a mapping', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            $headerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:p><w:r><w:t>Header Text</w:t></w:r></w:p>'
                . '</w:hdr>';

            $sourceRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
                . '</Relationships>';

            // Create source ZIP with header
            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            $sourceZip->addFromString('word/header1.xml', $headerXml);
            $sourceZip->addFromString('word/_rels/document.xml.rels', $sourceRelsXml);
            $sourceZip->close();

            // Create target ZIP
            $targetRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>';

            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/document.xml', '<w:document/>');
            $targetZip->addFromString('word/_rels/document.xml.rels', $targetRelsXml);
            $targetZip->close();

            // Reopen both
            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            $targetRelsDom = createDomFromXml($targetRelsXml);
            $sourceRelsDom = createDomFromXml($sourceRelsXml);
            $idTracker = new IdTracker();
            $copier = new HeaderFooterCopier();

            // Act
            $map = $copier->copy(
                $sourceZip,
                $targetZip,
                $targetRelsDom,
                $sourceRelsDom,
                $idTracker,
            );

            // Assert
            expect($map)->toBeInstanceOf(HeaderFooterMap::class);
            // The source had rId3 as header -- it should be mapped
            expect($map->getNewRelId('rId3'))->not->toBeNull();

            $sourceZip->close();
            $targetZip->close();
        });

        it('returns an empty map when the source has no headers or footers', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            $sourceRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>';

            $targetRelsXml = $sourceRelsXml;

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            $sourceZip->addFromString('word/_rels/document.xml.rels', $sourceRelsXml);
            $sourceZip->close();

            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/_rels/document.xml.rels', $targetRelsXml);
            $targetZip->close();

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            $targetRelsDom = createDomFromXml($targetRelsXml);
            $sourceRelsDom = createDomFromXml($sourceRelsXml);
            $idTracker = new IdTracker();
            $copier = new HeaderFooterCopier();

            // Act
            $map = $copier->copy(
                $sourceZip,
                $targetZip,
                $targetRelsDom,
                $sourceRelsDom,
                $idTracker,
            );

            // Assert
            expect($map)->toBeInstanceOf(HeaderFooterMap::class);
            expect($map->mappings)->toBeEmpty();

            $sourceZip->close();
            $targetZip->close();
        });

        it('throws on path traversal in header/footer target', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            $sourceRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="../../../etc/passwd"/>'
                . '</Relationships>';

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            $sourceZip->addFromString('word/_rels/document.xml.rels', $sourceRelsXml);
            $sourceZip->close();

            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/document.xml', '<w:document/>');
            $targetZip->close();

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            $targetRelsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $sourceRelsDom = createDomFromXml($sourceRelsXml);
            $idTracker = new IdTracker();
            $copier = new HeaderFooterCopier();

            // Act + Assert
            expect(fn () => $copier->copy($sourceZip, $targetZip, $targetRelsDom, $sourceRelsDom, $idTracker))
                ->toThrow(InvalidSourceException::class);

            $sourceZip->close();
            $targetZip->close();
        });
    });
});
