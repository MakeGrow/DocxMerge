# Low-Level Design: DocxMerge

## 1. Class Specifications

### 1.1 DocxMerger (Public Facade)

**Namespace**: `DocxMerge\DocxMerger`
**Layer**: Public API
**Responsibility**: Single entry point for the library. Validates template, normalizes inputs, and delegates to the orchestrator.

```php
final class DocxMerger
{
    private readonly LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null);

    public function merge(
        string $templatePath,
        array $merges,               // array<string, string|MergeDefinition>
        string $outputPath,
        ?MergeOptions $options = null,
    ): MergeResult;

    private function validateTemplate(string $templatePath): void;
    private function normalizeDefinitions(array $merges): array; // list<MergeDefinition>
}
```

**Behavior**:
1. Defaults `$logger` to `NullLogger` if null.
2. Validates template exists and is a valid ZIP.
3. Converts string entries in `$merges` to `MergeDefinition(markerName, sourcePath, sectionIndex: null)`.
4. Creates `MergeOrchestrator` with default concrete services.
5. Delegates to `$orchestrator->execute()`.

### 1.2 MergeOrchestrator

**Namespace**: `DocxMerge\Merge\MergeOrchestrator`
**Layer**: Orchestration
**Responsibility**: Coordinates the full pipeline without containing OOXML domain logic.

```php
final class MergeOrchestrator
{
    // 12 interface dependencies + XmlHelper, all nullable with defaults
    public function __construct(...);

    public function execute(
        string $templatePath,
        array $definitions,          // list<MergeDefinition>
        string $outputPath,
        MergeOptions $options,
    ): MergeResult;

    private function createWorkingCopy(string $templatePath, string $outputPath, MergeOptions $options): string;
    private function loadRequiredPart(ZipArchive $zip, string $partName, string $templatePath): DOMDocument;
    private function loadOptionalPart(ZipArchive $zip, string $partName): ?DOMDocument;
    private function createEmptyNumberingDom(): DOMDocument;
    private function processDefinition(MergeDefinition $def, MergeContext $ctx, MergeOptions $opt): void;
    private function buildStyleMap(?DOMDocument $source, DOMDocument $target): StyleMap;
    private function buildNumberingMap(?DOMDocument $source, DOMDocument $target, string $xml, IdTracker $t): NumberingMap;
    private function mergeStyles(DOMDocument $target, StyleMap $map): int;
    private function mergeNumbering(DOMDocument $target, NumberingMap $map): void;
    private function serializeNodesToXml(array $nodes, DOMDocument $owner): string;
    private function serializeDom(ZipArchive $zip, string $part, DOMDocument $dom): void;
    private function moveToOutput(string $temp, string $output): void;
}
```

**Pipeline Phases**:
1. Create working copy of template (temp file).
2. Open ZIP archive.
3. Load destination DOMs (document, styles, numbering, rels, content types).
4. Initialize `IdTracker` from target state.
5. Create `MergeContext`.
6. For each `MergeDefinition`: execute `processDefinition()`.
7. Post-processing: resequence numbering, preserve text spaces.
8. Serialize DOMs back to ZIP.
9. Update content types, validate, close ZIP.
10. Move temp file to output path.

### 1.3 MergeContext

**Namespace**: `DocxMerge\Merge\MergeContext`
**Layer**: Orchestration
**Responsibility**: Encapsulates all mutable state for a single merge operation.

```php
final class MergeContext
{
    // Constructor: targetZip, documentDom, stylesDom, numberingDom, relsDom,
    //              contentTypesDom, idTracker, sourceCache (all readonly)

    private array $errors = [];     // list<string>
    private array $warnings = [];   // list<string>
    private array $stats = [];      // array<string, int>

    public function addError(string $message): void;
    public function addWarning(string $message): void;
    public function incrementStat(string $key, int $amount = 1): void;
    public function getErrors(): array;
    public function getWarnings(): array;
    public function getStats(): array;
}
```

### 1.4 MarkerLocator

**Namespace**: `DocxMerge\Marker\MarkerLocator`
**Layer**: Domain Service
**Interface**: `MarkerLocatorInterface`

```php
interface MarkerLocatorInterface
{
    public function locate(DOMDocument $documentDom, string $markerName, string $markerPattern): ?MarkerLocation;
}
```

**Algorithm**:
1. Query all `w:p` elements via XPath `//w:p`.
2. For each paragraph, concatenate all descendant `w:t` node values to reconstruct full text.
3. Match reconstructed text against the marker pattern (e.g., `${CONTENT}`).
4. If found, return `MarkerLocation` with the paragraph `DOMElement` and the `w:t` nodes composing the marker.
5. If not found, return null.

This algorithm handles fragmented markers where Word splits `${CONTENT}` across multiple `w:r`/`w:t` elements (e.g., `$`, `{CONTENT`, `}`).

> **Note**: `MarkerLocator::locate()` throws `MergeException` if the XPath query fails due to an invalid or corrupted DOM structure. Callers should be prepared to handle this exception.

### 1.5 ContentExtractor

**Namespace**: `DocxMerge\Content\ContentExtractor`
**Layer**: Domain Service
**Interface**: `ContentExtractorInterface`

```php
interface ContentExtractorInterface
{
    public function extract(DOMDocument $sourceDom, ?int $sectionIndex = null): ExtractedContent;
    public function countSections(DOMDocument $sourceDom): int;
}
```

**Full Document Extraction** (`sectionIndex = null`):
1. Iterate children of `w:body`.
2. Identify all `w:sectPr` elements (both direct children and those inside `w:pPr`).
3. The last `w:sectPr` is stored as `finalSectPr` and excluded from content nodes.
4. Intermediate `w:sectPr` direct children of `w:body` are wrapped in `w:p > w:pPr > w:sectPr` (no debug text, `w:pPr` as first child).
5. Intermediate `w:sectPr` inside `w:pPr` are preserved intact, including `headerReference` and `footerReference`.
6. All other body children are collected as content nodes.

**Section-Specific Extraction** (`sectionIndex != null`):
1. Count total sections.
2. If `sectionIndex >= totalSections`, throw `InvalidSourceException`.
3. Determine section boundaries from `w:sectPr` positions.
4. Extract only nodes belonging to the specified section.
5. The section's `w:sectPr` becomes `finalSectPr`.

### 1.6 StyleMerger

**Namespace**: `DocxMerge\Style\StyleMerger`
**Layer**: Domain Service
**Interface**: `StyleMergerInterface`

```php
interface StyleMergerInterface
{
    public function buildMap(DOMDocument $sourceStylesDom, DOMDocument $targetStylesDom): StyleMap;
    public function merge(DOMDocument $targetStylesDom, StyleMap $styleMap): int;
}
```

**Hash-Based Comparison Algorithm**:
1. For each style in the target, normalize XML (remove `w:styleId`, `w:customStyle`, `w:default`, `w:name`, `w:aliases`, normalize whitespace) and compute SHA-256 hash.
2. Build index: `array<string, string>` where key = hash, value = existing styleId.
3. For each source style:
   a. Compute the same normalized hash.
   b. If hash exists in index: reuse existing target ID (mark as `reuseExisting`).
   c. If hash does not exist and ID does not conflict: import with original ID.
   d. If hash does not exist and ID conflicts: generate new sequential numeric ID (from 1000+).
4. During merge: import non-reused styles using `DocumentFragment` for batch insertion. Update `basedOn`, `next`, `link` references.

### 1.7 NumberingMerger

**Namespace**: `DocxMerge\Numbering\NumberingMerger`
**Layer**: Domain Service
**Interface**: `NumberingMergerInterface`

```php
interface NumberingMergerInterface
{
    public function buildMap(DOMDocument $src, DOMDocument $tgt, string $contentXml, IdTracker $tracker): NumberingMap;
    public function merge(DOMDocument $targetDom, NumberingMap $map): int;
}
```

**Ordered Insertion**:
- `w:abstractNum`: `insertBefore($node, $firstNumElement)` to place before any existing `w:num`.
- `w:num`: `appendChild($node)` to place after all `w:abstractNum`.

### 1.8 NumberingResequencer

**Namespace**: `DocxMerge\Numbering\NumberingResequencer`
**Layer**: Domain Service
**Interface**: `NumberingResequencerInterface`

```php
interface NumberingResequencerInterface
{
    public function resequence(DOMDocument $numberingDom, DOMDocument $documentDom): void;
}
```

**Resequencing Algorithm**:
1. Collect all `w:abstractNum` and `w:num` from the DOM.
2. Remove all from parent.
3. Build old-to-new ID maps.
4. Re-insert in correct order: all `w:abstractNum` (IDs: 0, 1, 2...) then all `w:num` (IDs: 1, 2, 3...).
5. Update `w:abstractNumId/@w:val` inside each `w:num`.
6. Update `w:numId/@w:val` in all `w:numPr` elements of `document.xml`.

### 1.9 RelationshipManager

**Namespace**: `DocxMerge\Relationship\RelationshipManager`
**Layer**: Domain Service
**Interface**: `RelationshipManagerInterface`

```php
interface RelationshipManagerInterface
{
    public function buildMap(DOMDocument $src, DOMDocument $tgt, string $contentXml, IdTracker $tracker): RelationshipMap;
    public function addRelationships(DOMDocument $targetRelsDom, RelationshipMap $map): void;
}
```

**Behavior**:
- Excludes header/footer relationships (handled by `HeaderFooterCopier`).
- Excludes structural relationships (styles, fontTable, theme, settings, webSettings).
- Filters to include only relationships referenced in the content XML (`strpos` for rId patterns).
- Detects duplicates by `Type+Target` combination and reuses existing IDs.

### 1.10 MediaCopier

**Namespace**: `DocxMerge\Media\MediaCopier`
**Layer**: Domain Service
**Interface**: `MediaCopierInterface`

```php
interface MediaCopierInterface
{
    public function copy(ZipArchive $src, ZipArchive $tgt, RelationshipMap $map, IdTracker $tracker): int;
}
```

**Behavior**:
- Iterates `RelationshipMap.getFilesToCopy()`.
- For each image relationship: reads binary content from source ZIP, generates new filename using `IdTracker.nextImageNumber()`, writes to target ZIP.
- Returns count of files copied.

### 1.11 HeaderFooterCopier

**Namespace**: `DocxMerge\HeaderFooter\HeaderFooterCopier`
**Layer**: Domain Service
**Interface**: `HeaderFooterCopierInterface`

```php
interface HeaderFooterCopierInterface
{
    public function copy(ZipArchive $src, ZipArchive $tgt, DOMDocument $tgtRels, DOMDocument $srcRels, IdTracker $tracker): HeaderFooterMap;
}
```

**Per-Header/Footer Processing**:
1. Read XML content and its local `.rels` from source ZIP.
2. Copy referenced images to target with new names.
3. Create new local `.rels` file with remapped image rIds.
4. Update image references in the header/footer XML.
5. Write XML to target with new sequential name.
6. Register relationship in target `document.xml.rels`.

### 1.12 SectionPropertiesApplier

**Namespace**: `DocxMerge\Section\SectionPropertiesApplier`
**Layer**: Domain Service
**Interface**: `SectionPropertiesApplierInterface`

```php
interface SectionPropertiesApplierInterface
{
    public function apply(DOMDocument $docDom, DOMElement $parent, ExtractedContent $content, HeaderFooterMap $hfMap): void;
}
```

**Two-Phase Application**:
1. **Intermediate sectPr**: After content insertion, scan inserted nodes for `w:sectPr` inside `w:pPr`. Update `headerReference/@r:id` and `footerReference/@r:id` using `HeaderFooterMap`.
2. **Final sectPr**: Apply `finalSectPr` to the target section governing the marker position. Remap header/footer references.

### 1.13 IdRemapper

**Namespace**: `DocxMerge\Remapping\IdRemapper`
**Layer**: Domain Service
**Interface**: `IdRemapperInterface`

```php
interface IdRemapperInterface
{
    public function remap(array $nodes, RelationshipMap $relMap, StyleMap $styleMap, NumberingMap $numMap, IdTracker $tracker, DOMDocument $targetDom): void;
}
```

**Remapping Order**:
1. `r:embed` and `r:id` attributes (relationship IDs).
2. `w:pStyle`, `w:rStyle`, `w:tblStyle` attributes (style IDs).
3. `w:numId` values inside `w:numPr` (two-pass with temp offset 999000).
4. `wp:docPr id` attributes (drawing object IDs).
5. `w:bookmarkStart` and `w:bookmarkEnd` `w:id` attributes (bookmark IDs).

**Two-Pass numId Remapping**:
- Pass 1: Replace old numId with `oldValue + 999000` (temporary).
- Pass 2: Replace temporary values with final new IDs from `NumberingMap`.
- This prevents collisions when a new ID matches an old ID not yet processed.

### 1.14 IdTracker

**Namespace**: `DocxMerge\Tracking\IdTracker`
**Layer**: Infrastructure

```php
final class IdTracker
{
    // 8 private counters: rId, image, headerFooter, styleId, numId, abstractNumId, docPrId, bookmarkId

    public static function initializeFromTarget(ZipArchive $zip, DOMDocument $rels, DOMDocument $doc, ?DOMDocument $numbering): self;

    public function nextRelationshipId(): string;     // 'rIdN'
    public function nextImageNumber(): int;
    public function nextHeaderFooterNumber(): int;
    public function nextStyleId(): int;                // >= 1000
    public function nextNumId(): int;
    public function nextAbstractNumId(): int;
    public function nextDocPrId(): int;
    public function nextBookmarkId(): int;
}
```

**Initialization**: Scans target DOMs and ZIP to determine maximum existing values for each counter type, then starts from max + 1.

### 1.15 XmlHelper

**Namespace**: `DocxMerge\Xml\XmlHelper`
**Layer**: Infrastructure

```php
final class XmlHelper
{
    public const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    public const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    // ... additional namespace constants

    public function createDom(string $xml): DOMDocument;
    public function createXpath(DOMDocument $dom): DOMXPath;
    public function preserveTextSpaces(DOMDocument $dom): void;
}
```

**DOM Creation Settings**:
- `preserveWhiteSpace = true`
- `formatOutput = false`
- `substituteEntities = false`
- Flags: `LIBXML_NONET | LIBXML_PARSEHUGE`
- `libxml_use_internal_errors(true)` for error collection
- No `@` error suppression

### 1.16 SourceDocumentCache

**Namespace**: `DocxMerge\Cache\SourceDocumentCache`
**Layer**: Infrastructure

```php
final class SourceDocumentCache
{
    private array $cache = [];  // array<string, SourceDocument>

    public function get(string $sourcePath): SourceDocument;
    public function clear(): void;
}
```

**Behavior**: On first access for a path, opens ZIP, parses document/styles/numbering/rels DOMs, counts sections, and stores in cache. Subsequent calls return cached instance.

---

## 2. DTO Specifications

### 2.1 MergeDefinition

```php
final class MergeDefinition
{
    public readonly string $markerName;
    public readonly string $sourcePath;
    public readonly ?int $sectionIndex;  // null = entire document
}
```

### 2.2 MergeOptions

```php
final class MergeOptions
{
    public readonly string $markerPattern;   // default: '/\$\{([A-Z_][A-Z0-9_]*)\}/'
    public readonly bool $strictMarkers;     // default: false
    public readonly bool $isReprocessing;    // default: false
}
```

### 2.3 MergeResult

```php
final class MergeResult
{
    public readonly bool $success;
    public readonly string $outputPath;
    public readonly array $errors;           // list<string>
    public readonly array $warnings;         // list<string>
    public readonly array $stats;            // array<string, int>
    public readonly float $executionTime;
}
```

### 2.4 MarkerLocation

```php
final class MarkerLocation
{
    public readonly DOMElement $paragraph;
    public readonly array $textNodes;        // list<DOMElement>
}
```

### 2.5 ExtractedContent

```php
final class ExtractedContent
{
    public readonly array $nodes;                      // list<DOMNode>
    public readonly ?DOMElement $finalSectPr;
    public readonly array $intermediateSectPrElements; // list<DOMElement>
    public readonly int $sectionCount;
}
```

### 2.6 StyleMap / StyleMapping

```php
final class StyleMap
{
    public readonly array $mappings;  // array<string, StyleMapping>

    public function getNewId(string $oldId): string;
    public function hasMapping(string $oldId): bool;
    public function isReused(string $oldId): bool;
    public function getStylesToImport(): array;
}

final class StyleMapping
{
    public readonly string $oldId;
    public readonly string $newId;
    public readonly string $type;
    public readonly DOMElement $node;
    public readonly bool $reuseExisting;
}
```

### 2.7 NumberingMap

```php
final class NumberingMap
{
    public readonly array $abstractNumMap;    // array<int, int>
    public readonly array $numMap;            // array<int, int>
    public readonly array $abstractNumNodes;  // list<DOMElement>
    public readonly array $numNodes;          // list<DOMElement>

    public function getNewNumId(int $old): ?int;
    public function getNewAbstractNumId(int $old): ?int;
}
```

### 2.8 RelationshipMap / RelationshipMapping

```php
final class RelationshipMap
{
    public readonly array $mappings;  // array<string, RelationshipMapping>

    public function getNewId(string $oldRId): ?string;
    public function getFileTarget(string $oldRId): ?string;
    public function getFilesToCopy(): array;
}

final class RelationshipMapping
{
    public readonly string $oldId;
    public readonly string $newId;
    public readonly string $type;
    public readonly string $target;
    public readonly string $newTarget;
    public readonly bool $needsFileCopy;
    public readonly bool $isExternal;
}
```

### 2.9 HeaderFooterMap / HeaderFooterMapping

```php
final class HeaderFooterMap
{
    public readonly array $mappings;  // array<string, HeaderFooterMapping>

    public function getNewRelId(string $oldRId): ?string;
    public function getNewFilename(string $oldRId): ?string;
}

final class HeaderFooterMapping
{
    public readonly string $oldId;
    public readonly string $newRelId;
    public readonly string $oldTarget;
    public readonly string $newFilename;
    public readonly string $type;
    public readonly bool $isHeader;
}
```

### 2.10 SourceDocument

```php
final class SourceDocument
{
    public readonly ZipArchive $zip;
    public readonly DOMDocument $documentDom;
    public readonly ?DOMDocument $stylesDom;
    public readonly ?DOMDocument $numberingDom;
    public readonly DOMDocument $relsDom;
    public readonly int $sectionCount;
}
```

### 2.11 ValidationResult

```php
final class ValidationResult
{
    public readonly array $errors;    // list<string>
    public readonly array $warnings;  // list<string>

    public function isValid(): bool;
}
```

---

## 3. ID Mapping Strategy

| ID Type | Initial Value Source | Generation Strategy | Collision Risk | Safety Net |
|---|---|---|---|---|
| rId | Max existing in target rels + 1 | Sequential increment | Low | Duplicate detection by Type+Target |
| styleId | Counter starting at 1000 | Hash-based lookup; sequential on conflict | Medium | Content-hash comparison |
| numId | Max existing in target numbering + 1 | Sequential increment | Low | Global resequencing |
| abstractNumId | Max existing + 1 | Sequential increment | Low | Global resequencing |
| docPr id | Max existing in document + 1 | Sequential increment | Low | None needed (unique per document) |
| bookmark id | Max existing in document + 1 | Sequential increment | Low | None needed |

---

## 4. Error Handling Strategy

### Fatal Errors (Throw and Abort)

| Exception | Trigger | Recovery |
|---|---|---|
| `InvalidTemplateException` | Template not found, not readable, not valid ZIP | None -- caller must fix input |
| `InvalidSourceException` | Source not found, invalid, sectionIndex out of bounds | None -- caller must fix input |
| `MarkerNotFoundException` | Marker not found, `strictMarkers = true` | None -- caller must fix template |
| `XmlParseException` | Malformed XML in any part | None -- document is corrupted |
| `ZipOperationException` | ZIP open/read/write failure | None -- filesystem issue |
| `MergeException` | General unrecoverable error | None -- unexpected state |

### Recoverable Issues (Warning, Continue)

| Situation | Action |
|---|---|
| Marker not found, `strictMarkers = false` | Log warning, skip marker |
| Header/footer file missing in source ZIP | Log warning, remove reference |
| Sections not covered by markers | Log warning |
| Style without `w:styleId` | Log warning, skip style |
| Orphan numId in document.xml | Log warning in validation |

### Cleanup on Failure

1. Close ZIP if opened.
2. Delete temporary working copy.
3. Re-throw typed exceptions (do not wrap `DocxMergeException` subtypes).
4. Wrap unexpected exceptions in `MergeException`.
