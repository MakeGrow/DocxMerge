<?php

declare(strict_types=1);

namespace DocxMerge\Media;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Tracking\IdTracker;
use ZipArchive;

/**
 * Copies media files from a source ZIP to a target ZIP.
 *
 * Iterates through the RelationshipMap and copies only the files that
 * are flagged as needing a file copy. Generates sequential filenames
 * using the IdTracker to avoid collisions with existing media files.
 */
final class MediaCopier implements MediaCopierInterface
{
    /**
     * Copies media files from source to target ZIP.
     *
     * Generates new sequential filenames (image1.png, image2.jpg) to avoid conflicts.
     * Only copies files that are referenced by relationships in the map.
     *
     * @param ZipArchive $sourceZip The source ZIP archive.
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param RelationshipMap $relationshipMap Map containing file targets to copy.
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return int Number of files copied.
     */
    public function copy(
        ZipArchive $sourceZip,
        ZipArchive $targetZip,
        RelationshipMap $relationshipMap,
        IdTracker $idTracker,
    ): int {
        $copied = 0;
        $filesToCopy = $relationshipMap->getFilesToCopy();

        foreach ($filesToCopy as $mapping) {
            $sourcePath = 'word/' . $mapping->target;
            $content = $sourceZip->getFromName($sourcePath);

            if ($content === false) {
                // Source file not found in archive -- skip silently.
                continue;
            }

            // Determine file extension from the original target path.
            $extension = pathinfo($mapping->target, PATHINFO_EXTENSION);
            $imageNumber = $idTracker->nextImageNumber();
            $newFilename = 'image' . $imageNumber . '.' . $extension;
            $targetPath = 'word/media/' . $newFilename;

            $targetZip->addFromString($targetPath, $content);
            $copied++;
        }

        return $copied;
    }
}
