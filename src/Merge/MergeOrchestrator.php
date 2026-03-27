<?php

declare(strict_types=1);

namespace DocxMerge\Merge;

use DocxMerge\Cache\SourceDocumentCache;
use DocxMerge\Content\ContentExtractor;
use DocxMerge\Content\ContentExtractorInterface;
use DocxMerge\ContentTypes\ContentTypesManager;
use DocxMerge\ContentTypes\ContentTypesManagerInterface;
use DocxMerge\Dto\MergeDefinition;
use DocxMerge\Dto\MergeOptions;
use DocxMerge\Dto\MergeResult;
use DocxMerge\Dto\NumberingMap;
use DocxMerge\Dto\StyleMap;
use DocxMerge\Exception\InvalidTemplateException;
use DocxMerge\Exception\MarkerNotFoundException;
use DocxMerge\Exception\MergeException;
use DocxMerge\HeaderFooter\HeaderFooterCopier;
use DocxMerge\HeaderFooter\HeaderFooterCopierInterface;
use DocxMerge\Marker\MarkerLocator;
use DocxMerge\Marker\MarkerLocatorInterface;
use DocxMerge\Media\MediaCopier;
use DocxMerge\Media\MediaCopierInterface;
use DocxMerge\Numbering\NumberingMerger;
use DocxMerge\Numbering\NumberingMergerInterface;
use DocxMerge\Numbering\NumberingResequencer;
use DocxMerge\Numbering\NumberingResequencerInterface;
use DocxMerge\Relationship\RelationshipManager;
use DocxMerge\Relationship\RelationshipManagerInterface;
use DocxMerge\Remapping\IdRemapper;
use DocxMerge\Remapping\IdRemapperInterface;
use DocxMerge\Section\SectionPropertiesApplier;
use DocxMerge\Section\SectionPropertiesApplierInterface;
use DocxMerge\Style\StyleMerger;
use DocxMerge\Style\StyleMergerInterface;
use DocxMerge\Tracking\IdTracker;
use DocxMerge\Validation\DocumentValidator;
use DocxMerge\Validation\DocumentValidatorInterface;
use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;
use DOMNode;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZipArchive;

/**
 * Coordinates the full DOCX merge pipeline.
 *
 * Orchestrates all domain services in a sequential pipeline: locate markers,
 * extract content, build ID maps, copy resources, merge definitions, import
 * nodes, remap IDs, apply section properties, and serialize back to ZIP.
 * Contains no OOXML domain logic -- only coordination.
 *
 * @see MergeContext
 */
final class MergeOrchestrator
{
    private readonly MarkerLocatorInterface $markerLocator;

    private readonly ContentExtractorInterface $contentExtractor;

    private readonly StyleMergerInterface $styleMerger;

    private readonly NumberingMergerInterface $numberingMerger;

    private readonly RelationshipManagerInterface $relationshipManager;

    private readonly MediaCopierInterface $mediaCopier;

    private readonly HeaderFooterCopierInterface $headerFooterCopier;

    private readonly SectionPropertiesApplierInterface $sectionPropertiesApplier;

    private readonly IdRemapperInterface $idRemapper;

    private readonly NumberingResequencerInterface $numberingResequencer;

    private readonly ContentTypesManagerInterface $contentTypesManager;

    private readonly DocumentValidatorInterface $documentValidator;

    private readonly XmlHelper $xmlHelper;

    private readonly LoggerInterface $logger;

    /**
     * @param MarkerLocatorInterface|null $markerLocator Marker locator service.
     * @param ContentExtractorInterface|null $contentExtractor Content extractor service.
     * @param StyleMergerInterface|null $styleMerger Style merger service.
     * @param NumberingMergerInterface|null $numberingMerger Numbering merger service.
     * @param RelationshipManagerInterface|null $relationshipManager Relationship manager service.
     * @param MediaCopierInterface|null $mediaCopier Media copier service.
     * @param HeaderFooterCopierInterface|null $headerFooterCopier Header/footer copier service.
     * @param SectionPropertiesApplierInterface|null $sectionPropertiesApplier Section properties applier.
     * @param IdRemapperInterface|null $idRemapper ID remapper service.
     * @param NumberingResequencerInterface|null $numberingResequencer Numbering resequencer service.
     * @param ContentTypesManagerInterface|null $contentTypesManager Content types manager.
     * @param DocumentValidatorInterface|null $documentValidator Document validator service.
     * @param XmlHelper|null $xmlHelper XML helper for DOM creation and whitespace preservation.
     * @param LoggerInterface|null $logger PSR-3 logger for diagnostic output. Defaults to NullLogger.
     */
    public function __construct(
        ?MarkerLocatorInterface $markerLocator = null,
        ?ContentExtractorInterface $contentExtractor = null,
        ?StyleMergerInterface $styleMerger = null,
        ?NumberingMergerInterface $numberingMerger = null,
        ?RelationshipManagerInterface $relationshipManager = null,
        ?MediaCopierInterface $mediaCopier = null,
        ?HeaderFooterCopierInterface $headerFooterCopier = null,
        ?SectionPropertiesApplierInterface $sectionPropertiesApplier = null,
        ?IdRemapperInterface $idRemapper = null,
        ?NumberingResequencerInterface $numberingResequencer = null,
        ?ContentTypesManagerInterface $contentTypesManager = null,
        ?DocumentValidatorInterface $documentValidator = null,
        ?XmlHelper $xmlHelper = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->markerLocator = $markerLocator ?? new MarkerLocator();
        $this->contentExtractor = $contentExtractor ?? new ContentExtractor();
        $this->styleMerger = $styleMerger ?? new StyleMerger();
        $this->numberingMerger = $numberingMerger ?? new NumberingMerger();
        $this->relationshipManager = $relationshipManager ?? new RelationshipManager();
        $this->mediaCopier = $mediaCopier ?? new MediaCopier();
        $this->headerFooterCopier = $headerFooterCopier ?? new HeaderFooterCopier();
        $this->sectionPropertiesApplier = $sectionPropertiesApplier ?? new SectionPropertiesApplier();
        $this->idRemapper = $idRemapper ?? new IdRemapper();
        $this->numberingResequencer = $numberingResequencer ?? new NumberingResequencer();
        $this->contentTypesManager = $contentTypesManager ?? new ContentTypesManager();
        $this->documentValidator = $documentValidator ?? new DocumentValidator();
        $this->xmlHelper = $xmlHelper ?? new XmlHelper();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Executes the full merge pipeline.
     *
     * Creates a working copy of the template, opens the ZIP, loads destination
     * DOMs, processes each MergeDefinition through the pipeline, performs
     * post-processing (resequence numbering, preserve text spaces), serializes
     * DOMs back to the ZIP, validates integrity, and moves the result to outputPath.
     *
     * @param string $templatePath Path to the template DOCX.
     * @param list<MergeDefinition> $definitions Normalized merge definitions.
     * @param string $outputPath Path for the output file.
     * @param MergeOptions $options Merge configuration.
     *
     * @return MergeResult Structured result with success status, errors, warnings, and stats.
     *
     * @throws InvalidTemplateException If the template cannot be opened.
     * @throws MarkerNotFoundException If a marker is not found and strictMarkers is enabled.
     * @throws MergeException On any unrecoverable merge error.
     */
    public function execute(
        string $templatePath,
        array $definitions,
        string $outputPath,
        MergeOptions $options,
    ): MergeResult {
        $startTime = microtime(true);

        $this->logger->debug('MergeOrchestrator: starting merge pipeline.', [
            'templatePath' => $templatePath,
            'definitionCount' => count($definitions),
        ]);

        // --- Phase 1: Create working copy of the template ---
        $tempPath = $this->createWorkingCopy($templatePath, $options);

        $targetZip = new ZipArchive();
        $opened = false;

        try {
            // --- Phase 2: Open the working ZIP ---
            $result = $targetZip->open($tempPath);
            if ($result !== true) {
                throw new InvalidTemplateException(
                    "Failed to open template as ZIP: {$templatePath}"
                );
            }
            $opened = true;

            // --- Phase 3: Load destination DOMs ---
            $documentDom = $this->loadRequiredPart($targetZip, 'word/document.xml', $templatePath);
            $stylesDom = $this->loadRequiredPart($targetZip, 'word/styles.xml', $templatePath);
            $relsDom = $this->loadRequiredPart($targetZip, 'word/_rels/document.xml.rels', $templatePath);
            $contentTypesDom = $this->loadRequiredPart($targetZip, '[Content_Types].xml', $templatePath);

            // numbering.xml is optional; create an empty one if absent
            $numberingDom = $this->loadOptionalPart($targetZip, 'word/numbering.xml');
            $needsNumberingRegistration = ($numberingDom === null);
            if ($needsNumberingRegistration) {
                $numberingDom = $this->createEmptyNumberingDom();
            }

            // --- Phase 4: Initialize IdTracker ---
            $idTracker = IdTracker::initializeFromTarget(
                $targetZip,
                $relsDom,
                $documentDom,
                $numberingDom,
            );

            // --- Phase 5: Create MergeContext ---
            $sourceCache = new SourceDocumentCache();
            $context = new MergeContext(
                targetZip: $targetZip,
                documentDom: $documentDom,
                stylesDom: $stylesDom,
                numberingDom: $numberingDom,
                relsDom: $relsDom,
                contentTypesDom: $contentTypesDom,
                idTracker: $idTracker,
                sourceCache: $sourceCache,
            );

            // --- Phase 5.5: Register numbering part if dynamically created ---
            if ($needsNumberingRegistration) {
                $this->contentTypesManager->registerRequiredPart(
                    $contentTypesDom,
                    $relsDom,
                    '/word/numbering.xml',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml',
                    'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering',
                    'numbering.xml',
                    $idTracker,
                );
            }

            // --- Phase 6: Process each MergeDefinition ---
            foreach ($definitions as $definition) {
                $this->processDefinition($definition, $context, $options);
            }

            // --- Phase 7: Post-processing ---
            $this->numberingResequencer->resequence($numberingDom, $documentDom);
            $this->xmlHelper->preserveTextSpaces($documentDom);

            // --- Phase 8: Serialize DOMs back to ZIP ---
            $this->serializeDom($targetZip, 'word/document.xml', $documentDom);
            $this->serializeDom($targetZip, 'word/styles.xml', $stylesDom);
            $this->serializeDom($targetZip, 'word/numbering.xml', $numberingDom);
            $this->serializeDom($targetZip, 'word/_rels/document.xml.rels', $relsDom);

            // --- Phase 9: Update Content_Types ---
            $this->contentTypesManager->update($contentTypesDom, $targetZip);
            $this->serializeDom($targetZip, '[Content_Types].xml', $contentTypesDom);

            // --- Phase 10: Validate integrity ---
            $validationResult = $this->documentValidator->validate($context);
            foreach ($validationResult->errors as $error) {
                $context->addWarning("Validation: {$error}");
            }
            foreach ($validationResult->warnings as $warning) {
                $context->addWarning("Validation: {$warning}");
            }

            // --- Phase 11: Close ZIP ---
            $targetZip->close();
            $opened = false;

            // --- Phase 12: Move to outputPath ---
            $this->moveToOutput($tempPath, $outputPath);

            // --- Phase 13: Return MergeResult ---
            $executionTime = microtime(true) - $startTime;

            return new MergeResult(
                success: true,
                outputPath: $outputPath,
                errors: $context->getErrors(),
                warnings: $context->getWarnings(),
                stats: $context->getStats(),
                executionTime: $executionTime,
            );
        } catch (\Throwable $e) {
            // Cleanup on failure: close ZIP and delete temp file
            if ($opened) {
                $targetZip->close();
            }

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            // Re-throw DocxMerge exceptions as-is; wrap others in MergeException
            if ($e instanceof \DocxMerge\Exception\DocxMergeException) {
                throw $e;
            }

            throw new MergeException(
                "Merge failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }
    }

    /**
     * Creates a temporary working copy of the template.
     *
     * If reprocessing mode is enabled and the output file already exists,
     * uses the existing output as the base instead of the original template.
     *
     * @param string $templatePath Path to the original template.
     * @param MergeOptions $options Merge options.
     *
     * @return string Path to the temporary working copy.
     *
     * @throws InvalidTemplateException If the template file cannot be copied.
     */
    private function createWorkingCopy(string $templatePath, MergeOptions $options): string
    {
        $sourcePath = $templatePath;

        // For reprocessing, use existing output as the base
        if ($options->isReprocessing && file_exists($templatePath)) {
            $sourcePath = $templatePath;
        }

        if (!file_exists($sourcePath)) {
            throw new InvalidTemplateException(
                "Template file does not exist: {$sourcePath}"
            );
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'docx_merge_');
        // @codeCoverageIgnoreStart
        if ($tempFile === false) {
            throw new MergeException('Failed to create temporary file.');
        }
        // @codeCoverageIgnoreEnd

        $tempPath = $tempFile . '.docx';

        $copied = copy($sourcePath, $tempPath);
        // @codeCoverageIgnoreStart
        if ($copied === false) {
            throw new InvalidTemplateException(
                "Failed to copy template to working location: {$sourcePath}"
            );
        }
        // @codeCoverageIgnoreEnd

        // Remove the tempnam file without extension
        if (file_exists($tempFile) && $tempFile !== $tempPath) {
            unlink($tempFile);
        }

        return $tempPath;
    }

    /**
     * Loads a required ZIP part as a DOMDocument.
     *
     * @param ZipArchive $zip The ZIP archive.
     * @param string $partName The ZIP entry name.
     * @param string $templatePath The original template path for error messages.
     *
     * @return DOMDocument The parsed DOM.
     *
     * @throws InvalidTemplateException If the part is missing or cannot be parsed.
     */
    private function loadRequiredPart(ZipArchive $zip, string $partName, string $templatePath): DOMDocument
    {
        $xml = $zip->getFromName($partName);
        if ($xml === false) {
            throw new InvalidTemplateException(
                "Required part '{$partName}' not found in template: {$templatePath}"
            );
        }

        return $this->xmlHelper->createDom($xml);
    }

    /**
     * Loads an optional ZIP part as a DOMDocument if present.
     *
     * @param ZipArchive $zip The ZIP archive.
     * @param string $partName The ZIP entry name.
     *
     * @return DOMDocument|null The parsed DOM, or null if the part does not exist.
     */
    private function loadOptionalPart(ZipArchive $zip, string $partName): ?DOMDocument
    {
        $xml = $zip->getFromName($partName);
        if ($xml === false) {
            return null;
        }

        return $this->xmlHelper->createDom($xml);
    }

    /**
     * Creates an empty numbering.xml DOM for templates without numbering.
     *
     * @return DOMDocument A minimal numbering DOM with the w: namespace.
     */
    private function createEmptyNumberingDom(): DOMDocument
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:numbering xmlns:w="' . XmlHelper::NS_W . '"/>';

        return $this->xmlHelper->createDom($xml);
    }

    /**
     * Processes a single MergeDefinition through the pipeline.
     *
     * Executes steps 7a through 7j from the solution data flow:
     * locate marker, get source, extract content, build maps, copy resources,
     * merge definitions, import nodes, remap IDs, apply section properties,
     * and remove the marker paragraph.
     *
     * @param MergeDefinition $definition The merge definition to process.
     * @param MergeContext $context The merge context with all DOMs and ZIP.
     * @param MergeOptions $options Merge configuration.
     *
     * @throws MarkerNotFoundException If the marker is not found and strictMarkers is enabled.
     */
    private function processDefinition(
        MergeDefinition $definition,
        MergeContext $context,
        MergeOptions $options,
    ): void {
        // --- Step 7a: Locate marker ---
        $location = $this->markerLocator->locate(
            $context->documentDom,
            $definition->markerName,
            $options->markerPattern,
        );

        if ($location === null) {
            if ($options->strictMarkers) {
                throw new MarkerNotFoundException(
                    "Marker '\${$definition->markerName}' not found in template."
                );
            }

            $context->addWarning(
                "Marker '\${$definition->markerName}' not found in template, skipping."
            );
            return;
        }

        // --- Step 7b: Get SourceDocument from cache ---
        $sourceDoc = $context->sourceCache->get($definition->sourcePath);

        // --- Step 7c: Extract content ---
        $extractedContent = $this->contentExtractor->extract(
            $sourceDoc->documentDom,
            $definition->sectionIndex,
        );

        // Build a serialized XML string of the extracted content for filtering
        $contentXml = $this->serializeNodesToXml($extractedContent->nodes, $sourceDoc->documentDom);

        // --- Step 7d: Build ID maps ---
        $styleMap = $this->buildStyleMap($sourceDoc->stylesDom, $context->stylesDom);
        $numberingMap = $this->buildNumberingMap(
            $sourceDoc->numberingDom,
            $context->numberingDom,
            $contentXml,
            $context->idTracker,
        );
        $relationshipMap = $this->relationshipManager->buildMap(
            $sourceDoc->relsDom,
            $context->relsDom,
            $contentXml,
            $context->idTracker,
        );

        // --- Step 7e: Copy resources ---
        $mediaTargetMap = $this->mediaCopier->copy(
            $sourceDoc->zip,
            $context->targetZip,
            $relationshipMap,
            $context->idTracker,
        );
        $context->incrementStat('images_copied', count($mediaTargetMap));

        // Update relationship targets after media renaming
        $relationshipMap = $relationshipMap->withUpdatedTargets($mediaTargetMap);

        $headerFooterMap = $this->headerFooterCopier->copy(
            $sourceDoc->zip,
            $context->targetZip,
            $context->relsDom,
            $sourceDoc->relsDom,
            $context->idTracker,
        );
        $context->incrementStat('headers_copied', count($headerFooterMap->mappings));

        // --- Step 7f: Merge definitions into target ---
        $stylesMerged = $this->mergeStyles($context->stylesDom, $styleMap);
        $context->incrementStat('styles_merged', $stylesMerged);

        $this->mergeNumbering($context->numberingDom, $numberingMap);
        $this->relationshipManager->addRelationships($context->relsDom, $relationshipMap);

        // --- Step 7g: Import nodes into target DOM ---
        $markerParagraph = $location->paragraph;
        $parentNode = $markerParagraph->parentNode;

        if (!$parentNode instanceof DOMElement) {
            $context->addError("Cannot insert content: marker paragraph has no parent element.");
            return;
        }

        /** @var list<DOMNode> $insertedNodes */
        $insertedNodes = [];
        foreach ($extractedContent->nodes as $node) {
            $imported = $context->documentDom->importNode($node, true);
            $parentNode->insertBefore($imported, $markerParagraph);
            $insertedNodes[] = $imported;
        }

        // --- Step 7h: Remap IDs in inserted nodes ---
        $this->idRemapper->remap(
            $insertedNodes,
            $relationshipMap,
            $styleMap,
            $numberingMap,
            $context->idTracker,
            $context->documentDom,
        );

        // --- Step 7i: Apply section properties ---
        $this->sectionPropertiesApplier->apply(
            $context->documentDom,
            $parentNode,
            $extractedContent,
            $headerFooterMap,
        );

        // --- Step 7j: Remove marker paragraph ---
        $parentNode->removeChild($markerParagraph);

        $context->incrementStat('markers_replaced');
    }

    /**
     * Builds a style map, handling the case where the source has no styles.
     *
     * @param DOMDocument|null $sourceStylesDom The source styles DOM, or null if absent.
     * @param DOMDocument $targetStylesDom The target styles DOM.
     *
     * @return StyleMap The computed style map.
     */
    private function buildStyleMap(?DOMDocument $sourceStylesDom, DOMDocument $targetStylesDom): StyleMap
    {
        if ($sourceStylesDom === null) {
            return new StyleMap([]);
        }

        return $this->styleMerger->buildMap($sourceStylesDom, $targetStylesDom);
    }

    /**
     * Builds a numbering map, handling the case where the source has no numbering.
     *
     * @param DOMDocument|null $sourceNumberingDom The source numbering DOM, or null if absent.
     * @param DOMDocument $targetNumberingDom The target numbering DOM.
     * @param string $contentXml The extracted content as XML string.
     * @param IdTracker $idTracker Shared ID counters.
     *
     * @return NumberingMap The computed numbering map.
     */
    private function buildNumberingMap(
        ?DOMDocument $sourceNumberingDom,
        DOMDocument $targetNumberingDom,
        string $contentXml,
        IdTracker $idTracker,
    ): NumberingMap {
        if ($sourceNumberingDom === null) {
            return new NumberingMap([], [], [], []);
        }

        return $this->numberingMerger->buildMap(
            $sourceNumberingDom,
            $targetNumberingDom,
            $contentXml,
            $idTracker,
        );
    }

    /**
     * Merges styles into the target, handling the case where the style map is empty.
     *
     * @param DOMDocument $targetStylesDom The target styles DOM.
     * @param StyleMap $styleMap The computed style map.
     *
     * @return int Number of styles merged.
     */
    private function mergeStyles(DOMDocument $targetStylesDom, StyleMap $styleMap): int
    {
        if (count($styleMap->mappings) === 0) {
            return 0;
        }

        return $this->styleMerger->merge($targetStylesDom, $styleMap);
    }

    /**
     * Merges numbering definitions into the target.
     *
     * @param DOMDocument $targetNumberingDom The target numbering DOM.
     * @param NumberingMap $numberingMap The computed numbering map.
     *
     * @return void
     */
    private function mergeNumbering(DOMDocument $targetNumberingDom, NumberingMap $numberingMap): void
    {
        if (count($numberingMap->abstractNumNodes) === 0 && count($numberingMap->numNodes) === 0) {
            return;
        }

        $this->numberingMerger->merge($targetNumberingDom, $numberingMap);
    }

    /**
     * Serializes a list of DOM nodes into an XML string for content filtering.
     *
     * Wraps nodes in a temporary root element so that the XML is well-formed.
     * The resulting string is used by NumberingMerger and RelationshipManager
     * to determine which IDs are actually referenced in the extracted content.
     *
     * @param list<DOMNode> $nodes The nodes to serialize.
     * @param DOMDocument $ownerDocument The document that owns the nodes.
     *
     * @return string The serialized XML string.
     */
    private function serializeNodesToXml(array $nodes, DOMDocument $ownerDocument): string
    {
        $xml = '';
        foreach ($nodes as $node) {
            $serialized = $ownerDocument->saveXML($node);
            if ($serialized !== false) {
                $xml .= $serialized;
            }
        }

        return $xml;
    }

    /**
     * Serializes a DOMDocument and writes it to the ZIP archive.
     *
     * @param ZipArchive $zip The target ZIP archive.
     * @param string $partName The ZIP entry name.
     * @param DOMDocument $dom The DOM to serialize.
     *
     * @return void
     *
     * @throws MergeException If serialization fails.
     */
    private function serializeDom(ZipArchive $zip, string $partName, DOMDocument $dom): void
    {
        $xml = $dom->saveXML();
        // @codeCoverageIgnoreStart
        if ($xml === false) {
            throw new MergeException("Failed to serialize DOM for part: {$partName}");
        }
        // @codeCoverageIgnoreEnd

        $zip->addFromString($partName, $xml);
    }

    /**
     * Moves the temporary working file to the final output path.
     *
     * Tries rename() first for efficiency; falls back to copy()+unlink()
     * when the paths are on different filesystems.
     *
     * @param string $tempPath Path to the temporary file.
     * @param string $outputPath Final output path.
     *
     * @throws MergeException If the file cannot be moved.
     */
    private function moveToOutput(string $tempPath, string $outputPath): void
    {
        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            $created = mkdir($outputDir, 0755, true);
            // @codeCoverageIgnoreStart
            if ($created === false) {
                throw new MergeException("Failed to create output directory: {$outputDir}");
            }
            // @codeCoverageIgnoreEnd
        }

        // Try rename first (atomic and fast on same filesystem)
        $renamed = rename($tempPath, $outputPath);
        if ($renamed === false) {
            // Fallback to copy+unlink for cross-filesystem moves
            $copied = copy($tempPath, $outputPath);
            // @codeCoverageIgnoreStart
            if ($copied === false) {
                throw new MergeException("Failed to move output file to: {$outputPath}");
            }
            // @codeCoverageIgnoreEnd
            unlink($tempPath);
        }
    }
}
