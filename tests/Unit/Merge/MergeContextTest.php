<?php

declare(strict_types=1);

/**
 * Tests for MergeContext.
 *
 * Verifies the mutable state container used during merge operations:
 * error accumulation, warning accumulation, and statistics tracking.
 */

use DocxMerge\Cache\SourceDocumentCache;
use DocxMerge\Merge\MergeContext;
use DocxMerge\Tracking\IdTracker;

describe('MergeContext', function (): void {
    describe('addError()', function (): void {
        it('accumulates error messages retrievable via getErrors()', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act
            $context->addError('Error one');
            $context->addError('Error two');

            // Assert
            expect($context->getErrors())->toBe(['Error one', 'Error two']);
        });
    });

    describe('addWarning()', function (): void {
        it('accumulates warning messages retrievable via getWarnings()', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act
            $context->addWarning('Warning A');
            $context->addWarning('Warning B');

            // Assert
            expect($context->getWarnings())->toBe(['Warning A', 'Warning B']);
        });
    });

    describe('incrementStat()', function (): void {
        it('increments existing stat counters and creates new ones', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act
            $context->incrementStat('markers_replaced');
            $context->incrementStat('markers_replaced');
            $context->incrementStat('images_copied', 5);
            $context->incrementStat('custom_stat', 3);

            // Assert
            $stats = $context->getStats();
            expect($stats['markers_replaced'])->toBe(2);
            expect($stats['images_copied'])->toBe(5);
            expect($stats['custom_stat'])->toBe(3);
        });
    });

    describe('getErrors()', function (): void {
        it('returns an empty list when no errors have been added', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act & Assert
            expect($context->getErrors())->toBe([]);
        });
    });

    describe('getWarnings()', function (): void {
        it('returns an empty list when no warnings have been added', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act & Assert
            expect($context->getWarnings())->toBe([]);
        });
    });

    describe('getStats()', function (): void {
        it('returns default stats when no increments have been made', function (): void {
            // Arrange
            $context = new MergeContext(
                targetZip: new ZipArchive(),
                documentDom: new DOMDocument(),
                stylesDom: new DOMDocument(),
                numberingDom: new DOMDocument(),
                relsDom: new DOMDocument(),
                contentTypesDom: new DOMDocument(),
                idTracker: new IdTracker(),
                sourceCache: new SourceDocumentCache(),
            );

            // Act & Assert
            $stats = $context->getStats();
            expect($stats['markers_replaced'])->toBe(0);
            expect($stats['images_copied'])->toBe(0);
            expect($stats['styles_merged'])->toBe(0);
            expect($stats['headers_copied'])->toBe(0);
        });
    });
});
