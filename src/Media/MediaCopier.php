<?php

declare(strict_types=1);

namespace DocxMerge\Media;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Tracking\IdTracker;
use DocxMerge\Zip\ZipHelper;
use ZipArchive;

/**
 * Copies media files from a source ZIP to a target ZIP.
 *
 * Iterates through the RelationshipMap and copies only the files that
 * are flagged as needing a file copy. Generates sequential filenames
 * using the IdTracker to avoid collisions with existing media files.
 *
 * All source paths are validated through ZipHelper::sanitizePath() to
 * prevent Zip Slip (directory traversal) attacks before reading from
 * the source archive.
 */
final class MediaCopier implements MediaCopierInterface
{
    /**
     * @param ZipHelper $zipHelper Helper for safe ZIP path operations.
     *
     * @see ZipHelper::sanitizePath()
     */
    public function __construct(
        private readonly ZipHelper $zipHelper = new ZipHelper(),
    ) {
    }

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
     * @return array<string, string> Map of original target paths to new target paths
     *                               (e.g. "media/photo.png" => "media/image1.png").
     */
    public function copy(
        ZipArchive $sourceZip,
        ZipArchive $targetZip,
        RelationshipMap $relationshipMap,
        IdTracker $idTracker,
    ): array {
        /** @var array<string, string> $targetMap */
        $targetMap = [];
        $filesToCopy = $relationshipMap->getFilesToCopy();

        foreach ($filesToCopy as $mapping) {
            // Validate target path to prevent directory traversal attacks
            $this->zipHelper->sanitizePath($mapping->target);

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
            $targetMap[$mapping->target] = 'media/' . $newFilename;
        }

        return $targetMap;
    }
}
