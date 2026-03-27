# High-Level Design: DocxMerge

## 1. Architecture Overview

DocxMerge follows a **layered architecture** with four distinct layers, each with clear responsibilities and dependency rules. Dependencies flow strictly downward: higher layers depend on lower layers, never the reverse.

```
+================================================================+
|                    Layer 1: Public API                          |
|                                                                 |
|  DocxMerger (facade)                                           |
|  MergeDefinition, MergeOptions, MergeResult (DTOs)             |
+================================================================+
                              |
                              v
+================================================================+
|                 Layer 2: Orchestration                          |
|                                                                 |
|  MergeOrchestrator (pipeline coordinator)                      |
|  MergeContext (per-operation state container)                   |
+================================================================+
                              |
                              v
+================================================================+
|                Layer 3: Domain Services                         |
|                                                                 |
|  MarkerLocator         ContentExtractor     StyleMerger        |
|  NumberingMerger       RelationshipManager  MediaCopier        |
|  HeaderFooterCopier    SectionPropertiesApplier                |
|  IdRemapper            NumberingResequencer                    |
|  ContentTypesManager   DocumentValidator                       |
+================================================================+
                              |
                              v
+================================================================+
|                Layer 4: Infrastructure                          |
|                                                                 |
|  XmlHelper             ZipHelper                               |
|  IdTracker             SourceDocumentCache                     |
+================================================================+
```

## 2. Design Principles

| Principle | Application |
|---|---|
| **Single Responsibility** | Each class has exactly one reason to change. StyleMerger knows nothing about numbering; NumberingMerger knows nothing about images. |
| **Dependency Inversion** | All domain services depend on interfaces. The orchestrator receives interfaces, not concrete classes. |
| **Open/Closed** | New resource types (charts, diagrams) can be added by creating new copier classes without modifying the orchestrator. |
| **Interface Segregation** | Interfaces are small and focused. `StyleMergerInterface` exposes only `buildMap()` and `merge()`. |
| **Stateless Services** | Domain services are stateless. All mutable state is encapsulated in `MergeContext`, created fresh per `merge()` call. |

## 3. Component Diagram

```
DocxMerger
  |
  +-- validates template
  +-- normalizes MergeDefinition[]
  +-- delegates to MergeOrchestrator
        |
        +-- MergeContext (state container)
        |     +-- targetZip: ZipArchive
        |     +-- documentDom, stylesDom, numberingDom, relsDom, contentTypesDom
        |     +-- idTracker: IdTracker
        |     +-- sourceCache: SourceDocumentCache
        |     +-- errors[], warnings[], stats{}
        |
        +-- Per-definition pipeline:
        |     |
        |     +-- [1] MarkerLocator -----> MarkerLocation | null
        |     |
        |     +-- [2] SourceDocumentCache -> SourceDocument (cached)
        |     |
        |     +-- [3] ContentExtractor ---> ExtractedContent
        |     |         (nodes, finalSectPr, intermediateSectPr)
        |     |
        |     +-- [4] StyleMerger --------> StyleMap
        |     +-- [4] NumberingMerger ----> NumberingMap
        |     +-- [4] RelationshipManager > RelationshipMap
        |     |
        |     +-- [5] MediaCopier --------> int (images copied)
        |     +-- [5] HeaderFooterCopier -> HeaderFooterMap
        |     |
        |     +-- [6] StyleMerger.merge()
        |     +-- [6] NumberingMerger.merge()
        |     +-- [6] RelationshipManager.addRelationships()
        |     |
        |     +-- [7] importNode() + insertBefore()
        |     |
        |     +-- [8] IdRemapper.remap()
        |     |
        |     +-- [9] SectionPropertiesApplier.apply()
        |     |
        |     +-- [10] removeChild(markerParagraph)
        |
        +-- Post-processing:
              +-- NumberingResequencer.resequence()
              +-- XmlHelper.preserveTextSpaces()
              +-- Serialize DOMs to ZIP
              +-- ContentTypesManager.update()
              +-- DocumentValidator.validate()
              +-- Close ZIP, move to output
```

## 4. Data Flow Diagram

```
                    +------------------+
                    |   Template.docx  |
                    |   (ZIP archive)  |
                    +--------+---------+
                             |
                    +--------v---------+
                    |  Working Copy    |
                    |  (temp file)     |
                    +--------+---------+
                             |
                    +--------v---------+
                    | Load Target DOMs |
                    | document.xml     |
                    | styles.xml       |
                    | numbering.xml    |
                    | doc.xml.rels     |
                    | Content_Types    |
                    +--------+---------+
                             |
              +--------------v--------------+
              |  For each MergeDefinition:  |
              |                             |
   +----------v-----------+     +-----------v----------+
   |   Source Document     |     |   Target DOMs       |
   |   (from cache or     |     |   (modified in       |
   |    opened fresh)     |     |    place)            |
   +----------+-----------+     +-----------+----------+
              |                             |
              |     +-----------+           |
              +---->| Extract   |           |
              |     | Content   |           |
              |     +-----+-----+           |
              |           |                 |
              |     +-----v-----+           |
              +---->| Build     |           |
              |     | ID Maps   |           |
              |     +-----+-----+           |
              |           |                 |
              |     +-----v-----+     +-----v-----+
              +---->| Copy      +---->| Merge     |
                    | Resources |     | into      |
                    +-----------+     | Target    |
                                      +-----+-----+
                                            |
                                      +-----v-----+
                                      | Import    |
                                      | Nodes +   |
                                      | Remap IDs |
                                      +-----+-----+
                                            |
                                      +-----v-----+
                                      | Apply     |
                                      | SectPr    |
                                      +-----+-----+
                                            |
              +-----------------------------+
              |
   +----------v-----------+
   |   Post-Processing    |
   |   - Resequence nums  |
   |   - Preserve spaces  |
   |   - Serialize DOMs   |
   |   - Update Content   |
   |     Types            |
   |   - Validate         |
   +----------+-----------+
              |
   +----------v-----------+
   |   Output.docx        |
   |   (final file)       |
   +----------------------+
```

## 5. Package Diagram

```
DocxMerge\                              (Root namespace)
  |
  +-- DocxMerger                        (Public facade)
  |
  +-- Dto\                              (Data Transfer Objects)
  |     +-- MergeDefinition
  |     +-- MergeOptions
  |     +-- MergeResult
  |     +-- ExtractedContent
  |     +-- SourceDocument
  |     +-- MarkerLocation
  |     +-- StyleMap, StyleMapping
  |     +-- NumberingMap
  |     +-- RelationshipMap, RelationshipMapping
  |     +-- HeaderFooterMap, HeaderFooterMapping
  |     +-- ValidationResult
  |
  +-- Exception\                        (Exception hierarchy)
  |     +-- DocxMergeException (abstract base)
  |     +-- InvalidTemplateException
  |     +-- InvalidSourceException
  |     +-- MarkerNotFoundException
  |     +-- XmlParseException
  |     +-- ZipOperationException
  |     +-- MergeException
  |
  +-- Merge\                            (Orchestration)
  |     +-- MergeOrchestrator
  |     +-- MergeContext
  |
  +-- Marker\                           (Marker detection)
  |     +-- MarkerLocatorInterface
  |     +-- MarkerLocator
  |
  +-- Content\                          (Content extraction)
  |     +-- ContentExtractorInterface
  |     +-- ContentExtractor
  |
  +-- Style\                            (Style merging)
  |     +-- StyleMergerInterface
  |     +-- StyleMerger
  |
  +-- Numbering\                        (Numbering merging)
  |     +-- NumberingMergerInterface
  |     +-- NumberingMerger
  |     +-- NumberingResequencerInterface
  |     +-- NumberingResequencer
  |
  +-- Relationship\                     (Relationship management)
  |     +-- RelationshipManagerInterface
  |     +-- RelationshipManager
  |
  +-- Media\                            (Media copying)
  |     +-- MediaCopierInterface
  |     +-- MediaCopier
  |
  +-- HeaderFooter\                     (Header/footer copying)
  |     +-- HeaderFooterCopierInterface
  |     +-- HeaderFooterCopier
  |
  +-- Section\                          (Section properties)
  |     +-- SectionPropertiesApplierInterface
  |     +-- SectionPropertiesApplier
  |
  +-- Remapping\                        (ID remapping)
  |     +-- IdRemapperInterface
  |     +-- IdRemapper
  |
  +-- ContentTypes\                     (Content types management)
  |     +-- ContentTypesManagerInterface
  |     +-- ContentTypesManager
  |
  +-- Validation\                       (Document validation)
  |     +-- DocumentValidatorInterface
  |     +-- DocumentValidator
  |
  +-- Xml\                              (XML utilities)
  |     +-- XmlHelper
  |
  +-- Zip\                              (ZIP utilities)
  |     +-- ZipHelper
  |
  +-- Tracking\                         (ID tracking)
  |     +-- IdTracker
  |
  +-- Cache\                            (Source caching)
        +-- SourceDocumentCache
```

## 6. Interface Dependencies

All domain services in Layer 3 are accessed through interfaces. The orchestrator depends only on the interfaces, and concrete implementations are injected (or defaulted) at construction time.

```
MergeOrchestrator
    |
    +-- depends on --> MarkerLocatorInterface
    +-- depends on --> ContentExtractorInterface
    +-- depends on --> StyleMergerInterface
    +-- depends on --> NumberingMergerInterface
    +-- depends on --> RelationshipManagerInterface
    +-- depends on --> MediaCopierInterface
    +-- depends on --> HeaderFooterCopierInterface
    +-- depends on --> SectionPropertiesApplierInterface
    +-- depends on --> IdRemapperInterface
    +-- depends on --> NumberingResequencerInterface
    +-- depends on --> ContentTypesManagerInterface
    +-- depends on --> DocumentValidatorInterface
    +-- depends on --> XmlHelper (concrete, infrastructure)
```

## 7. OOXML Parts Manipulation Map

| OOXML Part | Read | Write | Responsible Service |
|---|---|---|---|
| `word/document.xml` | Template + Source | Target | ContentExtractor, MarkerLocator, IdRemapper, SectionPropertiesApplier, NumberingResequencer |
| `word/styles.xml` | Template + Source | Target | StyleMerger |
| `word/numbering.xml` | Template + Source | Target | NumberingMerger, NumberingResequencer |
| `word/_rels/document.xml.rels` | Template + Source | Target | RelationshipManager, HeaderFooterCopier |
| `[Content_Types].xml` | Template | Target | ContentTypesManager |
| `word/media/*` | Source | Target | MediaCopier |
| `word/header*.xml` | Source | Target | HeaderFooterCopier |
| `word/footer*.xml` | Source | Target | HeaderFooterCopier |
| `word/_rels/header*.xml.rels` | Source | Target | HeaderFooterCopier |

## 8. Exception Hierarchy

```
\RuntimeException
  |
  +-- DocxMergeException (abstract)
        |
        +-- InvalidTemplateException
        |     Template not found, not readable, or not a valid ZIP/DOCX.
        |
        +-- InvalidSourceException
        |     Source not found, invalid, or section index out of bounds.
        |
        +-- MarkerNotFoundException
        |     Marker not found in template (only when strictMarkers=true).
        |
        +-- XmlParseException
        |     Malformed XML in any document part.
        |
        +-- ZipOperationException
        |     Failed to open, read, or write a ZIP entry.
        |
        +-- MergeException
              General merge error (validation failure, unexpected state).
```

## 9. Technology Stack

| Component | Choice | Justification |
|---|---|---|
| ZIP manipulation | `ext-zip` (ZipArchive) | Native PHP extension, no external dependency, stable API |
| XML parsing | `ext-dom` (DOMDocument + DOMXPath) | Full namespace support, `importNode()` for cross-document merge, complete DOM manipulation |
| Logging | `psr/log` ^3.0 | Framework-agnostic, industry standard, zero coupling |
| Testing | Pest v3 | Expressive syntax, datasets, describe/it blocks, PHPStan compatible |
| Static Analysis | PHPStan ^2.0 level 8 | Maximum type rigor, generics support |
| Code Style | php-cs-fixer | PSR-12 enforcement |
