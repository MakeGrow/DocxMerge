<?php

declare(strict_types=1);

namespace DocxMerge\Media;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Tracking\IdTracker;
use ZipArchive;

/**
 * Contract for copying media files from a source ZIP to a target ZIP.
 *
 * Implementations must generate new sequential filenames to avoid conflicts
 * and only copy files that are referenced by relationships in the map.
 */
interface MediaCopierInterface
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
    ): int;
}
