<?php

declare(strict_types=1);

namespace DocxMerge\Tracking;

use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Tracks ID counters across a merge operation to prevent collisions.
 *
 * Initialized from the target document state and incremented as new
 * IDs are assigned during merge processing.
 */
final class IdTracker
{
    private int $relationshipIdCounter = 0;
    private int $imageCounter = 0;
    private int $headerFooterCounter = 0;
    private int $styleIdCounter = 1000;
    private int $numIdCounter = 0;
    private int $abstractNumIdCounter = 0;
    private int $docPrIdCounter = 0;
    private int $bookmarkIdCounter = 0;

    /**
     * Initializes all counters by scanning the target ZIP and DOMs.
     *
     * Scans relationship IDs, media files, header/footer files, drawing
     * object properties, bookmark IDs, and numbering IDs to find the
     * maximum existing value for each counter type.
     *
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param DOMDocument $relsDom The target document.xml.rels DOM.
     * @param DOMDocument $documentDom The target document.xml DOM.
     * @param DOMDocument|null $numberingDom The target numbering.xml DOM.
     *
     * @return self Initialized tracker with counters set to the max existing values.
     */
    public static function initializeFromTarget(
        ZipArchive $targetZip,
        DOMDocument $relsDom,
        DOMDocument $documentDom,
        ?DOMDocument $numberingDom,
    ): self {
        $tracker = new self();

        $tracker->scanRelationshipIds($relsDom);
        $tracker->scanMediaFiles($targetZip);
        $tracker->scanHeaderFooterFiles($targetZip);
        $tracker->scanDocPrIds($documentDom);
        $tracker->scanBookmarkIds($documentDom);

        if ($numberingDom !== null) {
            $tracker->scanNumberingIds($numberingDom);
        }

        return $tracker;
    }

    /**
     * Scans the rels DOM for the highest rId numeric value.
     *
     * @param DOMDocument $relsDom The relationships DOM to scan.
     */
    private function scanRelationshipIds(DOMDocument $relsDom): void
    {
        $xpath = new DOMXPath($relsDom);
        $xpath->registerNamespace('rel', XmlHelper::NS_REL);

        $nodes = $xpath->query('//rel:Relationship/@Id');

        // @codeCoverageIgnoreStart
        if ($nodes === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $max = 0;
        foreach ($nodes as $node) {
            $value = $node->nodeValue ?? '';
            if (preg_match('/^rId(\d+)$/', $value, $matches) === 1) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $this->relationshipIdCounter = $max;
    }

    /**
     * Scans the ZIP for the highest image number in word/media/.
     *
     * @param ZipArchive $targetZip The target ZIP archive.
     */
    private function scanMediaFiles(ZipArchive $targetZip): void
    {
        $entryNames = self::listZipEntries($targetZip);
        $max = 0;

        foreach ($entryNames as $name) {
            if (preg_match('/^word\/media\/image(\d+)\./', $name, $matches) === 1) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $this->imageCounter = $max;
    }

    /**
     * Scans the ZIP for the highest header/footer file number.
     *
     * @param ZipArchive $targetZip The target ZIP archive.
     */
    private function scanHeaderFooterFiles(ZipArchive $targetZip): void
    {
        $entryNames = self::listZipEntries($targetZip);
        $max = 0;

        foreach ($entryNames as $name) {
            if (preg_match('/^word\/(?:header|footer)(\d+)\.xml$/', $name, $matches) === 1) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        $this->headerFooterCounter = $max;
    }

    /**
     * Lists all entry names in a ZIP archive safely.
     *
     * ZipArchive::count() throws ValueError when no archive is open,
     * so this method catches that case and returns an empty list.
     *
     * @param ZipArchive $zip The ZIP archive to list.
     *
     * @return list<string> The entry names in the archive.
     */
    private static function listZipEntries(ZipArchive $zip): array
    {
        try {
            $count = $zip->count();
        } catch (\ValueError) {
            // No archive is open -- nothing to scan.
            return [];
        }

        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Scans the document DOM for the highest wp:docPr id value.
     *
     * @param DOMDocument $documentDom The document.xml DOM to scan.
     */
    private function scanDocPrIds(DOMDocument $documentDom): void
    {
        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('wp', XmlHelper::NS_WP);

        $nodes = $xpath->query('//wp:docPr/@id');

        // @codeCoverageIgnoreStart
        if ($nodes === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $max = 0;
        foreach ($nodes as $node) {
            $num = (int) ($node->nodeValue ?? '0');
            if ($num > $max) {
                $max = $num;
            }
        }

        $this->docPrIdCounter = $max;
    }

    /**
     * Scans the document DOM for the highest bookmark w:id value.
     *
     * @param DOMDocument $documentDom The document.xml DOM to scan.
     */
    private function scanBookmarkIds(DOMDocument $documentDom): void
    {
        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('w', XmlHelper::NS_W);

        $nodes = $xpath->query('//w:bookmarkStart/@w:id');

        // @codeCoverageIgnoreStart
        if ($nodes === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $max = 0;
        foreach ($nodes as $node) {
            $num = (int) ($node->nodeValue ?? '0');
            if ($num > $max) {
                $max = $num;
            }
        }

        $this->bookmarkIdCounter = $max;
    }

    /**
     * Scans the numbering DOM for the highest numId and abstractNumId values.
     *
     * @param DOMDocument $numberingDom The numbering.xml DOM to scan.
     */
    private function scanNumberingIds(DOMDocument $numberingDom): void
    {
        $xpath = new DOMXPath($numberingDom);
        $xpath->registerNamespace('w', XmlHelper::NS_W);

        // Scan w:num/@w:numId for max numId
        $numNodes = $xpath->query('//w:num/@w:numId');
        if ($numNodes !== false) {
            $max = 0;
            foreach ($numNodes as $node) {
                $num = (int) ($node->nodeValue ?? '0');
                if ($num > $max) {
                    $max = $num;
                }
            }
            $this->numIdCounter = $max;
        }

        // Scan w:abstractNum/@w:abstractNumId for max abstractNumId
        $abstractNodes = $xpath->query('//w:abstractNum/@w:abstractNumId');
        if ($abstractNodes !== false) {
            $max = 0;
            foreach ($abstractNodes as $node) {
                $num = (int) ($node->nodeValue ?? '0');
                if ($num > $max) {
                    $max = $num;
                }
            }
            $this->abstractNumIdCounter = $max;
        }
    }

    /**
     * Returns the next available relationship ID.
     *
     * @return string The next rId (e.g., "rId5").
     */
    public function nextRelationshipId(): string
    {
        return 'rId' . (++$this->relationshipIdCounter);
    }

    /**
     * Returns the next available image number.
     *
     * @return int The next image number.
     */
    public function nextImageNumber(): int
    {
        return ++$this->imageCounter;
    }

    /**
     * Returns the next available header/footer file number.
     *
     * @return int The next header/footer number.
     */
    public function nextHeaderFooterNumber(): int
    {
        return ++$this->headerFooterCounter;
    }

    /**
     * Returns the next available style ID for renamed styles.
     *
     * @return int The next style ID (starts from 1000).
     */
    public function nextStyleId(): int
    {
        return ++$this->styleIdCounter;
    }

    /**
     * Returns the next available numId.
     *
     * @return int The next numId.
     */
    public function nextNumId(): int
    {
        return ++$this->numIdCounter;
    }

    /**
     * Returns the next available abstractNumId.
     *
     * @return int The next abstractNumId.
     */
    public function nextAbstractNumId(): int
    {
        return ++$this->abstractNumIdCounter;
    }

    /**
     * Returns the next available drawing object property ID.
     *
     * @return int The next docPr id.
     */
    public function nextDocPrId(): int
    {
        return ++$this->docPrIdCounter;
    }

    /**
     * Returns the next available bookmark ID.
     *
     * @return int The next bookmark id.
     */
    public function nextBookmarkId(): int
    {
        return ++$this->bookmarkIdCounter;
    }
}
