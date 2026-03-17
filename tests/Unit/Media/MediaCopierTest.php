<?php

declare(strict_types=1);

/**
 * Tests for MediaCopier.
 *
 * Verifies that media files are copied from source to target ZIP with
 * sequential filenames, and that only files referenced in the
 * RelationshipMap are copied.
 */

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\RelationshipMapping;
use DocxMerge\Media\MediaCopier;
use DocxMerge\Tracking\IdTracker;

describe('MediaCopier', function (): void {
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
        it('copies referenced image files from source to target with sequential names', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            // Create a source ZIP with an image
            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            // 1x1 pixel transparent PNG
            $pngData = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
            );
            $sourceZip->addFromString('word/media/image1.png', $pngData);
            $sourceZip->close();

            // Create an empty target ZIP
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/document.xml', '<w:document/>');
            $targetZip->close();

            // Reopen both for the copier
            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            $relationshipMap = new RelationshipMap([
                'rId2' => new RelationshipMapping(
                    oldId: 'rId2',
                    newId: 'rId10',
                    type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
                    target: 'media/image1.png',
                    newTarget: 'media/image1.png',
                    needsFileCopy: true,
                    isExternal: false,
                ),
            ]);

            $idTracker = new IdTracker();
            $copier = new MediaCopier();

            // Act
            $count = $copier->copy($sourceZip, $targetZip, $relationshipMap, $idTracker);

            // Assert
            expect($count)->toBe(1);

            $sourceZip->close();
            $targetZip->close();

            // Verify the image was written to the target
            $verifyZip = new ZipArchive();
            $verifyZip->open($targetPath);
            $hasImage = false;
            for ($i = 0; $i < $verifyZip->numFiles; $i++) {
                /** @var string $name */
                $name = $verifyZip->getNameIndex($i);
                if (str_starts_with($name, 'word/media/image')) {
                    $hasImage = true;
                    break;
                }
            }
            expect($hasImage)->toBeTrue();
            $verifyZip->close();
        });

        it('skips relationships that do not need file copy', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            $sourceZip->addFromString('word/document.xml', '<w:document/>');
            $sourceZip->close();

            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/document.xml', '<w:document/>');
            $targetZip->close();

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            // External hyperlink -- needsFileCopy is false
            $relationshipMap = new RelationshipMap([
                'rId5' => new RelationshipMapping(
                    oldId: 'rId5',
                    newId: 'rId15',
                    type: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
                    target: 'https://example.com',
                    newTarget: 'https://example.com',
                    needsFileCopy: false,
                    isExternal: true,
                ),
            ]);

            $idTracker = new IdTracker();
            $copier = new MediaCopier();

            // Act
            $count = $copier->copy($sourceZip, $targetZip, $relationshipMap, $idTracker);

            // Assert
            expect($count)->toBe(0);

            $sourceZip->close();
            $targetZip->close();
        });

        it('returns zero when the relationship map is empty', function () use (&$tempFiles): void {
            // Arrange
            $sourcePath = tempnam(sys_get_temp_dir(), 'src_zip_') . '.zip';
            $targetPath = tempnam(sys_get_temp_dir(), 'tgt_zip_') . '.zip';
            /** @var list<string> $tempFiles */
            $tempFiles[] = $sourcePath;
            $tempFiles[] = $targetPath;

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath, ZipArchive::CREATE);
            $sourceZip->addFromString('word/document.xml', '<w:document/>');
            $sourceZip->close();

            $targetZip = new ZipArchive();
            $targetZip->open($targetPath, ZipArchive::CREATE);
            $targetZip->addFromString('word/document.xml', '<w:document/>');
            $targetZip->close();

            $sourceZip = new ZipArchive();
            $sourceZip->open($sourcePath);
            $targetZip = new ZipArchive();
            $targetZip->open($targetPath);

            $relationshipMap = new RelationshipMap([]);
            $idTracker = new IdTracker();
            $copier = new MediaCopier();

            // Act
            $count = $copier->copy($sourceZip, $targetZip, $relationshipMap, $idTracker);

            // Assert
            expect($count)->toBe(0);

            $sourceZip->close();
            $targetZip->close();
        });
    });
});
