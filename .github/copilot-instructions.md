# DocxMerge -- GitHub Copilot Instructions

## Project Overview

**mkgrow/docx-merge** is a framework-agnostic PHP 8.1+ Composer library. Its sole purpose is to merge DOCX documents by substituting `${MARKER}` placeholders in a template with content extracted from other `.docx` source files.

- **Namespace**: `DocxMerge\` maps to `src/`. `DocxMerge\Tests\` maps to `tests/`.
- **Runtime dependencies**: `psr/log ^3.0` and PHP extensions `zip`, `dom`, `xml`, `mbstring`.
- **No framework coupling**: no Laravel, Symfony, or any framework class in `src/`.
- **Quality gate**: `composer ci` (PHPStan level 8 + PSR-12 + test coverage >= 90%).

### Architecture

4-layer architecture with strict downward dependency flow. Full design in `docs/system-design/`.

```
Layer 1: Public API        -- DocxMerger (facade), DTOs (MergeDefinition, MergeOptions, MergeResult)
Layer 2: Orchestration     -- MergeOrchestrator (pipeline), MergeContext (per-operation state)
Layer 3: Domain Services   -- 12 stateless services behind interfaces
Layer 4: Infrastructure    -- XmlHelper, ZipHelper, IdTracker, SourceDocumentCache
```

| Component | Role |
|---|---|
| `DocxMerger` | Public facade: validates template, normalizes inputs, delegates to orchestrator |
| `Merge/MergeOrchestrator` | Coordinates the full pipeline without OOXML domain logic |
| `Merge/MergeContext` | Per-operation mutable state: DOMs, ZIP, IdTracker, stats, errors, warnings |
| `Marker/MarkerLocator` | Locates `${MARKER}` placeholders via paragraph text reconstruction (handles fragmented markers) |
| `Content/ContentExtractor` | Extracts body content from source documents, supports section-specific extraction |
| `Style/StyleMerger` | Merges styles using SHA-256 content hash for O(1) conflict resolution |
| `Numbering/NumberingMerger` | Merges numbering definitions with ordered insertion (abstractNum before num) |
| `Numbering/NumberingResequencer` | Post-merge: renumbers all IDs sequentially and enforces DOM ordering |
| `Relationship/RelationshipManager` | Manages `rId` relationships, filters by content reference, deduplicates by Type+Target |
| `Remapping/IdRemapper` | Remaps rIds, styleIds, numIds (two-pass), docPr ids, bookmark ids in imported content |
| `Media/MediaCopier` | Copies image files with sequential renaming |
| `HeaderFooter/HeaderFooterCopier` | Copies headers/footers with local .rels and embedded images |
| `Section/SectionPropertiesApplier` | Applies final and intermediate sectPr with header/footer rId remapping |
| `ContentTypes/ContentTypesManager` | Maintains `[Content_Types].xml` entries for new parts |
| `Validation/DocumentValidator` | Post-merge validation: rIds, numIds, styles, file existence |
| `Xml/XmlHelper` | DOM creation (LIBXML_NONET), XPath with OOXML namespaces, text space preservation |
| `Zip/ZipHelper` | ZipArchive read/write with path sanitization (Zip Slip prevention) |
| `Tracking/IdTracker` | 8 ID counters initialized from target state, prevents collisions |
| `Cache/SourceDocumentCache` | Caches parsed source documents (ZIP + DOMs) by file path |

### Key Architectural Decisions

Documented in `docs/system-design/06-adrs/architecture-decision-records.md`:

- **ADR-001**: Stateless services with MergeContext (eliminates state leakage between merge calls)
- **ADR-002**: Explicit marker-section association via MergeDefinition (replaces fragile heuristics)
- **ADR-003**: SHA-256 content hash for style comparison (O(1) instead of O(N*M))
- **ADR-004**: Defense-in-depth numbering (ordered insertion + resequencing)
- **ADR-005**: Source document caching (avoids reopening same ZIP multiple times)
- **ADR-006**: DOM node list over XML string for extracted content (preserves namespaces)
- **ADR-007**: Paragraph-level text reconstruction for fragmented markers
- **ADR-008**: Defer abstractNum deduplication to v2 (known bug risk)
- **ADR-011**: Two-pass numId remapping with temp offset 999000

### Exception Hierarchy

`DocxMergeException` (abstract, extends `\RuntimeException`) -> `InvalidTemplateException`, `InvalidSourceException`, `MarkerNotFoundException`, `XmlParseException`, `ZipOperationException`, `MergeException`.

All package exceptions must extend `DocxMergeException`. Never throw raw `\RuntimeException` or `\Exception`.

### Commands

```bash
composer test                # Pest v3 full suite
composer test --filter=Name  # Single describe block or test
composer test:coverage       # Suite with coverage gate (minimum 90%)
composer analyse             # PHPStan level 8 on src/ and tests/
composer format              # PSR-12 fixes via php-cs-fixer
composer format:check        # Check PSR-12 without modifying
composer ci                  # Full gate: analyse + format:check + test:coverage
```

### Project Structure

```
docs/
  system-design/
    01-problem-statement/    # Design brief: scope, requirements, assumptions
    02-requirements/         # Full requirements spec + use case diagrams
    03-hld/                  # High-level design: layers, components, data flow
    04-lld/                  # Low-level design: class specs + sequence diagrams
    05-capacity-plan/        # Scale limits, memory/CPU/IO, recommendations
    06-adrs/                 # 13 architecture decision records

src/                         # Library source (DocxMerge\ namespace)
  Cache/                     # Source document caching
  Content/                   # Content extraction from source documents
  ContentTypes/              # [Content_Types].xml management
  Dto/                       # Immutable value objects (readonly properties)
  Exception/                 # DocxMergeException hierarchy
  HeaderFooter/              # Header/footer copying
  Marker/                    # Marker location in templates
  Media/                     # Media file copying
  Merge/                     # Merge orchestration and context
  Numbering/                 # Numbering merge and resequencing
  Relationship/              # Relationship management and remapping
  Remapping/                 # ID remapping coordination
  Section/                   # Section properties handling
  Style/                     # Style merge and conflict resolution
  Tracking/                  # ID tracking across operations
  Validation/                # Document validation
  Xml/                       # XML/DOM utilities
  Zip/                       # ZipArchive utilities
tests/
  Unit/                      # Pure unit tests (no filesystem, in-memory XML)
  Integration/               # Real .docx fixtures
    Fixtures/                # .docx test input files
  Pest.php                   # Global helpers (fixture, createDomFromXml, createXpathWithNamespaces)
```

---

## Extreme Programming Methodology

This project follows Extreme Programming (XP) strictly. All development and planning must respect these practices.

### Pair Programming: Driver and Navigator

- **Driver (Copilot/AI agent)**: Writes all code, tests, and commits. Implements exactly what the task defines. Reports progress with evidence. Waits for Navigator approval before advancing.
- **Navigator (User)**: Sets direction, reviews deliveries, approves or rejects. Has absolute veto over every delivery, commit, and advancement.

No task advancement or commit occurs without explicit Navigator approval.

### TDD: Red-Green-Refactor Cycle

Every behavioral change follows the Red-Green-Refactor cycle without exception:

- **RED**: Write a failing test first. No production code. The test MUST fail.
- **GREEN**: Write the minimum production code to make the failing test pass. Nothing extra.
- **REFACTOR**: Clean up code while keeping all tests green. No behavior change. This step is mandatory, not optional.

### The Nine XP Practices

| Practice | Rule |
|---|---|
| **Test-First** | Tests are written before production code. RED must exist before GREEN. |
| **Simple Design** | Implement only what the current task requires. No speculative abstractions. |
| **Small Releases** | Sprint file updated after every task. One commit per completed phase. |
| **Continuous Refactoring** | REFACTOR tasks are mandatory after every GREEN. |
| **Collective Ownership** | PHPDoc and inline comments on every class and public method. All in English. |
| **Coding Standards** | PSR-12, PHPStan level 8, `composer ci` must be green before any phase commit. |
| **Continuous Integration** | Full suite (`composer test`) must pass after every GREEN and REFACTOR task. |
| **Pair Programming** | Navigator (user) approves every delivery before the sprint file is updated. |
| **Sustainable Pace** | Implement only what the ticket defines. Scope creep is a violation. |

### Halt Conditions

Stop and notify the Navigator when:

1. A task depends on another task that is not yet complete.
2. A file, interface, or method referenced in the task does not exist in the codebase.
3. The task scope is ambiguous and requires significant assumptions.
4. A test fails for a reason outside the current task scope.
5. The planned change would break callers not covered by the ticket.
6. A TDD violation is detected (GREEN without RED, REFACTOR with red suite).
7. PHPStan compliance requires `mixed` types or suppression that cannot be resolved.

---

## Code Review

When reviewing code, apply the following rules. These are consolidated from the project instruction files in `.github/instructions/`.

### PHP Code Quality

**Strict Typing**
- Every PHP file: `<?php` then `declare(strict_types=1);` then namespace.
- All parameter types, return types, and property types declared.
- Never use `mixed` without narrowing guards.

**Class Design**
- All classes `final` (exception: `DocxMergeException` is `abstract`).
- Default visibility `private`. `public` only for interface implementations and API surface.
- Services accept `Psr\Log\LoggerInterface` via constructor, defaulting to `NullLogger`.
- Prefer `readonly` properties for values set only in the constructor.
- Prefer `match` over `switch`. Named arguments for boolean flags.
- Constructor promotion for DTOs and value objects.

**Naming Conventions**
- Classes: `PascalCase`. Interfaces: `PascalCase` (no `I` prefix).
- Methods/variables: `camelCase`. Constants: `UPPER_SNAKE_CASE`.
- Boolean methods: `is`, `has`, `can`, `should` prefix.

**Error Handling**
- All exceptions extend `DocxMergeException`. Use the specific exception for the domain.
- Never throw raw `\Exception`, `\RuntimeException`, or `\InvalidArgumentException`.
- Never use the `@` error-suppression operator.
- Early returns to reduce nesting. Fail-fast validation.

**Dependency Management**
- Type-hint interfaces, not concrete implementations.
- Inject dependencies via constructor. No service locators.
- No framework coupling in `src/`.

**Immutability**
- DTOs are immutable value objects with `readonly` properties.
- Methods that transform state return new instances.
- No static mutable state.

### PHPStan Level 8

- All arrays typed: `array<string, int>`, `list<DOMElement>`, `array{key: string}`.
- Never `@var` to silence PHPStan -- fix the type.
- Never `@phpstan-ignore-next-line` without a comment explaining why.
- Strict comparisons only (`===`, `!==`). `in_array()` with `true` as third parameter.
- Handle `false` returns explicitly (`array_search`, `strpos`, `ZipArchive::getFromName`).
- No `empty()` on typed variables. No `compact()` or `extract()`.
- New code must not add PHPStan baseline entries.
- Use `@template`, `@phpstan-param`, `@phpstan-return` when native types are insufficient.

### Documentation

- PHPDoc on every class (responsibility and purpose).
- PHPDoc on every public and protected method.
- `@param` with meaning and constraints, not just type repetition.
- `@return` always present except `void`/`never`.
- `@throws` for every exception that can propagate.
- Inline comments explain WHY, not WHAT.
- All PHPDoc and comments in English.

### Testing (Pest v3)

**Structure**
- `it()` for all tests. Descriptions: lowercase verb, imperative outcome, English.
- Group with `describe()` blocks. One assertion focus per test.
- Arrange-Act-Assert pattern. All closures declare return type `void`.
- `expect()` API, not PHPUnit assertions. Chain expectations.

**Unit vs Integration**
- Unit tests (`tests/Unit/`): no filesystem, no real `.docx`. In-memory XML via `createDomFromXml()`.
- Integration tests (`tests/Integration/`): real fixtures from `tests/Integration/Fixtures/`. Output to `sys_get_temp_dir()`. Cleanup in `afterEach()`.
- Directory structure mirrors `src/`: `src/Style/StyleMerger.php` -> `tests/Unit/Style/StyleMergerTest.php`.

**Global Helpers** (defined in `tests/Pest.php` -- never duplicate):
- `fixture(string $name): string` -- integration tests only.
- `createDomFromXml(string $xml): DOMDocument` -- parses with `LIBXML_NONET`.
- `createXpathWithNamespaces(DOMDocument $dom): DOMXPath` -- all OOXML namespaces.

**Coverage**: minimum 90%. Every public method tested. Happy path + error/edge cases.

### OOXML / DOCX Manipulation

**Security**
- `LIBXML_NONET` on every `loadXML()`. Flag missing flags as critical.
- No raw user input in XPath queries.

**ID Remapping** (when merging across documents):
- `rId` values in `.rels` and referencing elements.
- `w:numId` and `w:abstractNumId` in `numbering.xml` and paragraphs.
- `w:styleId` in `styles.xml` and `w:pStyle`/`w:rStyle` references.
- `wp:docPr id` and bookmark `w:id` must be unique.

**Content Types**
- Every new ZIP part needs a `[Content_Types].xml` entry.
- `PartName` starts with `/`. `Default` by extension, `Override` for specific parts.

**Relationships**
- Every `rId` in XML must exist in the `.rels` file.
- Every `.rels` `Target` must point to an existing ZIP file.
- External targets require `TargetMode="External"`.
- Never remove structural relationships (styles, settings, fontTable, theme).

**Node Import**
- Never append a node directly from one `DOMDocument` to another.
- Always `$targetDom->importNode($sourceNode, deep: true)` first.
- Check `instanceof DOMElement` after import.

**Whitespace**
- `xml:space="preserve"` on `w:t` with leading or trailing spaces.
- Word silently strips spaces without this attribute.

**Document Structure**
- Last child of `w:body` must be `w:sectPr`. Move it after appending content.
- Intermediate `w:sectPr` inside `w:pPr`, not direct children of `w:body`.
- `w:tc` must contain at least one `w:p`.
- `w:abstractNum` before `w:num` in `numbering.xml`.
- `w:docDefaults` first child of `w:styles`.

**Namespace Handling**
- Declare all namespaces on root element before serialization.
- Use class constants for namespace URIs. Register in XPath.

**ZipArchive**
- `open()` returns `true` or integer error code -- check explicitly.
- `getFromName()` returns `false` on failure -- check before use.
- No `@` suppression.

**DOM Serialization**
- `saveXML()` may strip child namespaces -- declare on root.
- `preserveWhiteSpace = true`, `formatOutput = false`.

### Review Red Flags

Flag these immediately during code review:

- Missing `declare(strict_types=1)`
- Class not `final` (except `DocxMergeException`)
- `loadXML()` without `LIBXML_NONET`
- Direct node append across `DOMDocument` instances
- Hardcoded `rId` values instead of remapped IDs
- Missing `[Content_Types].xml` update for new ZIP parts
- `w:t` with spaces but no `xml:space="preserve"`
- Content appended after `w:sectPr` in `w:body`
- `@` error suppression anywhere
- Raw `\RuntimeException` or `\Exception` instead of `DocxMergeException` hierarchy
- `fixture()` used in unit tests
- Mutable DTO (missing `readonly`)
- PHPDoc in Portuguese
- `mixed` without narrowing guard
- `empty()` on typed variables
