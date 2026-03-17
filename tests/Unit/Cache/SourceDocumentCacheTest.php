<?php

declare(strict_types=1);

/**
 * Tests for SourceDocumentCache.
 *
 * Verifies caching behavior for source document parsing, including
 * cache hits, cache misses, clearing, and error handling for
 * non-existent files.
 */

use DocxMerge\Cache\SourceDocumentCache;
use DocxMerge\Dto\SourceDocument;
use DocxMerge\Exception\InvalidSourceException;

describe('SourceDocumentCache', function (): void {
    describe('get()', function (): void {
        it('returns a SourceDocument for a valid DOCX file', function (): void {
            // Arrange
            $cache = new SourceDocumentCache();

            // Act
            $doc = $cache->get(fixture('source-simple.docx'));

            // Assert
            expect($doc)->toBeInstanceOf(SourceDocument::class);
        });

        it('returns the same instance on repeated calls for the same path', function (): void {
            // Arrange
            $cache = new SourceDocumentCache();

            // Act
            $doc1 = $cache->get(fixture('source-simple.docx'));
            $doc2 = $cache->get(fixture('source-simple.docx'));

            // Assert -- same object reference means cache hit
            expect($doc1)->toBe($doc2);
        });

        it('throws InvalidSourceException for a non-existent file', function (): void {
            // Arrange
            $cache = new SourceDocumentCache();

            // Act + Assert
            expect(fn (): SourceDocument => $cache->get('/non/existent/file.docx'))
                ->toThrow(InvalidSourceException::class);
        });
    });

    describe('clear()', function (): void {
        it('forces re-parsing on subsequent calls after clearing', function (): void {
            // Arrange
            $cache = new SourceDocumentCache();
            $doc1 = $cache->get(fixture('source-simple.docx'));

            // Act
            $cache->clear();
            $doc2 = $cache->get(fixture('source-simple.docx'));

            // Assert -- different object reference means cache was cleared
            expect($doc1)->not->toBe($doc2);
        });
    });
});
