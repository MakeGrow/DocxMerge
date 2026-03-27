# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.0-beta] - 2026-03-27

### Added

- `DocxMerger` public facade with `merge()` method for substituting `${MARKER}` placeholders in DOCX templates with content from source documents.
- `MergeDefinition` DTO for binding a marker name to a source file path with optional section targeting via `sectionIndex`.
- `MergeOptions` DTO for configuring merge behavior: custom marker pattern, strict marker mode, and reprocessing mode.
- `MergeResult` DTO returning success status, output path, processing stats, errors, warnings, and execution time.
- 13-phase merge pipeline coordinated by `MergeOrchestrator` with per-operation state in `MergeContext`.
- `MarkerLocator` for locating `${MARKER}` placeholders via paragraph text reconstruction.
- `ContentExtractor` for extracting body content with multi-section support.
- `StyleMerger` with SHA-256 content hash for O(1) style conflict resolution.
- `NumberingMerger` and `NumberingResequencer` for merging and resequencing numbering definitions.
- `RelationshipManager` for managing rId relationships with Type+Target deduplication.
- `IdRemapper` for remapping rIds, styleIds, numIds, docPr ids, and bookmark ids across merged content.
- `MediaCopier` for copying media files with sequential renaming and Zip Slip prevention.
- `HeaderFooterCopier` for copying headers/footers with local relationship handling.
- `SectionPropertiesApplier` for applying section properties with header/footer rId remapping.
- `ContentTypesManager` for maintaining `[Content_Types].xml` with dynamic part registration.
- `DocumentValidator` for post-merge validation of rIds, numIds, and style references.
- `XmlHelper` with XXE prevention (`LIBXML_NONET`), XPath factory, and whitespace preservation.
- `ZipHelper` with path sanitization for Zip Slip prevention.
- `IdTracker` for managing 8 ID counters initialized from target document state.
- `SourceDocumentCache` for caching parsed source documents by file path.
- Exception hierarchy with 6 concrete types: `InvalidTemplateException`, `InvalidSourceException`, `MarkerNotFoundException`, `XmlParseException`, `ZipOperationException`, `MergeException`.
- Complete system design documentation (design brief, requirements, HLD, LLD, capacity plan, 13 ADRs).
- GitHub Actions CI pipeline with PHPStan level 8, PSR-12, and test coverage gate.
- 169 tests (18 unit files, 8 integration files) with 306 assertions and 95.2% line coverage.
