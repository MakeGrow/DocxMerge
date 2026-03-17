<?php

declare(strict_types=1);

namespace DocxMerge;

use DocxMerge\Dto\MergeDefinition;
use DocxMerge\Dto\MergeOptions;
use DocxMerge\Dto\MergeResult;
use DocxMerge\Exception\InvalidTemplateException;
use DocxMerge\Merge\MergeOrchestrator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZipArchive;

/**
 * Public facade and composition root for the DocxMerge library.
 *
 * Provides a single entry point for merging source DOCX documents into a
 * template by replacing ${MARKER} placeholders. Normalizes caller inputs,
 * validates the template, instantiates all internal services, and delegates
 * the actual merge pipeline to MergeOrchestrator.
 *
 * This is the only class where concrete service implementations are created.
 * The logger is propagated to services that support logging.
 *
 * @see MergeOrchestrator
 */
final class DocxMerger
{
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger PSR-3 logger for diagnostic output. Defaults to NullLogger.
     */
    public function __construct(
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Merges source documents into a template by replacing markers.
     *
     * Each entry in $merges maps a marker name to either a source file path
     * (string) or a MergeDefinition with explicit section targeting. Strings
     * are automatically normalized to MergeDefinition with sectionIndex=null,
     * meaning the entire source document is used.
     *
     * @param string $templatePath Absolute path to the template DOCX file.
     * @param array<string, string|MergeDefinition> $merges Map of marker name to source path or MergeDefinition.
     * @param string $outputPath Absolute path for the output DOCX file.
     * @param MergeOptions|null $options Optional merge configuration. Defaults to MergeOptions defaults.
     *
     * @return MergeResult Structured result with success flag, stats, errors, warnings.
     *
     * @throws InvalidTemplateException If the template does not exist or is not a valid DOCX.
     * @throws Exception\InvalidSourceException If any source file is not a valid DOCX.
     * @throws Exception\MarkerNotFoundException If a marker is not found and strictMarkers is enabled.
     * @throws Exception\MergeException On any unrecoverable merge error.
     */
    public function merge(
        string $templatePath,
        array $merges,
        string $outputPath,
        ?MergeOptions $options = null,
    ): MergeResult {
        $options = $options ?? new MergeOptions();

        $this->logger->debug('Starting merge operation.', [
            'templatePath' => $templatePath,
            'outputPath' => $outputPath,
            'mergeCount' => count($merges),
        ]);

        // Validate that the template exists and is a valid ZIP before proceeding
        $this->validateTemplate($templatePath);

        // Normalize string entries into MergeDefinition objects
        $definitions = $this->normalizeDefinitions($merges);

        // Create the orchestrator with default concrete services
        $orchestrator = new MergeOrchestrator();

        return $orchestrator->execute(
            $templatePath,
            $definitions,
            $outputPath,
            $options,
        );
    }

    /**
     * Validates that the template file exists and is a valid ZIP archive.
     *
     * @param string $templatePath Path to the template file.
     *
     * @throws InvalidTemplateException If the file does not exist or is not a valid ZIP.
     */
    private function validateTemplate(string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new InvalidTemplateException(
                "Template file does not exist: {$templatePath}"
            );
        }

        $zip = new ZipArchive();
        $result = $zip->open($templatePath, ZipArchive::RDONLY);

        if ($result !== true) {
            throw new InvalidTemplateException(
                "Template file is not a valid DOCX/ZIP: {$templatePath}"
            );
        }

        $zip->close();
    }

    /**
     * Normalizes the merges array into a list of MergeDefinition objects.
     *
     * String values are converted to MergeDefinition with sectionIndex=null,
     * meaning the entire source document will be used. MergeDefinition values
     * are passed through as-is.
     *
     * @param array<string, string|MergeDefinition> $merges Raw merges from the caller.
     *
     * @return list<MergeDefinition> Normalized definitions ready for the orchestrator.
     */
    private function normalizeDefinitions(array $merges): array
    {
        $definitions = [];

        foreach ($merges as $markerName => $value) {
            if ($value instanceof MergeDefinition) {
                $definitions[] = $value;
            } else {
                // String path: create a MergeDefinition with the full document
                $definitions[] = new MergeDefinition(
                    markerName: $markerName,
                    sourcePath: $value,
                    sectionIndex: null,
                );
            }
        }

        return $definitions;
    }
}
