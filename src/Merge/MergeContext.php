<?php

declare(strict_types=1);

namespace DocxMerge\Merge;

use DocxMerge\Cache\SourceDocumentCache;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use ZipArchive;

/**
 * Encapsulates all mutable state for a single merge operation.
 *
 * Created at the beginning of execute() and passed to each service.
 * Replaces the 14 instance properties of the monolithic reference.
 */
final class MergeContext
{
    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $stats = [
        'markers_replaced' => 0,
        'images_copied' => 0,
        'styles_merged' => 0,
        'headers_copied' => 0,
    ];

    /**
     * @param ZipArchive $targetZip The target ZIP archive.
     * @param DOMDocument $documentDom The target document.xml DOM.
     * @param DOMDocument $stylesDom The target styles.xml DOM.
     * @param DOMDocument $numberingDom The target numbering.xml DOM.
     * @param DOMDocument $relsDom The target document.xml.rels DOM.
     * @param DOMDocument $contentTypesDom The [Content_Types].xml DOM.
     * @param IdTracker $idTracker Shared ID counters.
     * @param SourceDocumentCache $sourceCache Source document cache.
     */
    public function __construct(
        public readonly ZipArchive $targetZip,
        public readonly DOMDocument $documentDom,
        public readonly DOMDocument $stylesDom,
        public readonly DOMDocument $numberingDom,
        public readonly DOMDocument $relsDom,
        public readonly DOMDocument $contentTypesDom,
        public readonly IdTracker $idTracker,
        public readonly SourceDocumentCache $sourceCache,
    ) {
    }

    /**
     * Adds a non-fatal error to the context.
     *
     * @param string $message The error message.
     *
     * @return void
     */
    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Adds a warning to the context.
     *
     * @param string $message The warning message.
     *
     * @return void
     */
    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Increments a statistics counter.
     *
     * @param string $key The stat key (e.g., 'markers_replaced').
     * @param int $amount The increment amount.
     *
     * @return void
     */
    public function incrementStat(string $key, int $amount = 1): void
    {
        if (!isset($this->stats[$key])) {
            $this->stats[$key] = 0;
        }
        $this->stats[$key] += $amount;
    }

    /**
     * Returns all accumulated errors.
     *
     * @return list<string> The error messages.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns all accumulated warnings.
     *
     * @return list<string> The warning messages.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Returns all statistics counters.
     *
     * @return array<string, int> The stats.
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
