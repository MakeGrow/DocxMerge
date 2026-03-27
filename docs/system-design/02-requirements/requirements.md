# Requirements Specification: DocxMerge

## 1. Introduction

### 1.1 Purpose

This document defines the complete functional and non-functional requirements for the `mkgrow/docx-merge` library, a framework-agnostic PHP 8.1+ Composer package that merges DOCX documents by substituting `${MARKER}` placeholders in a template with content extracted from source `.docx` files.

### 1.2 Background

The library replaces a monolithic 7,357-line reference implementation (`DocxMergeByMarkers.php`) that was coupled to Laravel, had zero tests, 14 documented bugs, and significant technical debt. The new implementation decomposes the monolith into ~20 single-responsibility classes with full test coverage and strict type safety.

### 1.3 Stakeholders

| Role | Responsibility |
|---|---|
| Library Consumer (Developer) | Integrates the library into PHP applications to merge DOCX documents |
| End User | Receives the merged DOCX output; expects correct formatting and content |
| Maintainer | Extends and maintains the library codebase |

---

## 2. Functional Requirements

### 2.1 Must Have (P0)

#### FR-01: Marker-Based Content Substitution

The library must replace `${MARKER}` placeholders in a template DOCX with body content extracted from source DOCX files. The marker pattern defaults to `/\$\{([A-Z_][A-Z0-9_]*)\}/` and is configurable via `MergeOptions`.

**Acceptance Criteria**:
- Given a template with `${CONTENT}` and a source document, the output contains the source body content in place of the marker.
- The marker paragraph is completely removed from the output.
- Content is inserted at the exact DOM position of the marker paragraph.

#### FR-02: Style Merging with Conflict Resolution

The library must copy styles from source documents to the target, resolving ID conflicts.

**Acceptance Criteria**:
- Styles identical by content (normalized XML hash) reuse the existing target ID.
- Styles with conflicting IDs but different content receive a new sequential numeric ID (starting from 1000).
- `basedOn`, `next`, and `link` references within imported styles are updated to reflect new IDs.
- Style comparison uses SHA-256 hash of normalized XML for O(1) lookup complexity.

#### FR-03: Numbering Merge with OOXML Ordering

The library must copy numbering definitions (`w:abstractNum` and `w:num`) from source to target, maintaining OOXML-required ordering.

**Acceptance Criteria**:
- Only numbering definitions actually referenced in extracted content are imported.
- All `w:abstractNum` elements precede all `w:num` elements in the output `numbering.xml`.
- `w:abstractNumId` references within `w:num` elements are updated correctly.
- A post-merge resequencing pass renumbers all IDs sequentially (abstractNumId: 0,1,2...; numId: 1,2,3...).

#### FR-04: Media File Copying

The library must copy image files from source to target ZIP with sequential renaming.

**Acceptance Criteria**:
- Only images referenced by relationships in the content are copied.
- Images receive new sequential names (image1.png, image2.jpg) to avoid conflicts.
- Relationship targets are updated to point to new filenames.

#### FR-05: Header/Footer Copying

The library must copy headers and footers from source to target, including their local relationships (embedded images).

**Acceptance Criteria**:
- Header/footer XML files are copied with new sequential names (header3.xml, footer4.xml).
- Local `.rels` files are created with remapped image relationship IDs.
- Images within headers/footers are copied to the target.
- New relationships are registered in `document.xml.rels`.

#### FR-06: Section Properties Application (Final Section)

The library must apply section properties (`w:sectPr`) from the last section of the source document to the template section where the marker was located.

**Acceptance Criteria**:
- Page layout (margins, size, orientation) from the source is applied to the target section.
- `headerReference` and `footerReference` within `sectPr` are remapped to the newly copied headers/footers.

#### FR-07: Intermediate Section Preservation

The library must preserve intermediate section properties in multi-section source documents, including their headers and footers.

**Acceptance Criteria**:
- Intermediate `w:sectPr` elements are encapsulated in `w:pPr` as the first child of `w:p` (no debug text, no `w:r`/`w:t`).
- Headers and footers of intermediate sections are copied and their `r:id` references remapped.
- No text artifacts (debug strings) appear in the output document.
- `w:pPr` ordering complies with OOXML specification (first child of `w:p`).

#### FR-08: Explicit Marker-Section Association

The library must allow callers to explicitly specify which section of a multi-section source document corresponds to which marker.

**Acceptance Criteria**:
- `MergeDefinition` accepts an optional `sectionIndex` (zero-based).
- When `sectionIndex` is null, the entire source document is used.
- When `sectionIndex` is specified, only that section's content is extracted.
- Invalid `sectionIndex` (exceeding available sections) throws `InvalidSourceException`.
- Multiple markers referencing the same source without explicit section indices and with `sectionIndex = null` emit an error.

#### FR-09: Relationship ID Remapping

The library must remap all `rId` references in imported content to avoid collisions with existing target relationships.

**Acceptance Criteria**:
- `r:embed` and `r:id` attributes are updated.
- Only relationships actually referenced in the content are imported.
- External relationships (hyperlinks) are handled correctly.
- Duplicate relationships (same Type+Target) reuse existing target IDs.

#### FR-10: Content Types Maintenance

The library must keep `[Content_Types].xml` consistent with all parts in the ZIP.

**Acceptance Criteria**:
- Default entries are added for new file extensions (png, jpeg, gif, emf, wmf).
- Override entries are added for new parts (headers, footers).
- No duplicate entries are created.

#### FR-11: Output Validation

The library must validate the integrity of the merged document before closing the ZIP.

**Acceptance Criteria**:
- Every `rId` in `document.xml` exists in `document.xml.rels`.
- Every relationship target points to an existing file in the ZIP.
- Every `w:numId` in paragraphs has a corresponding `w:num` in `numbering.xml`.
- Every `w:pStyle`/`w:rStyle` references an existing style in `styles.xml`.
- Validation issues are reported as warnings (non-blocking).

#### FR-12: Fragmented Marker Detection

The library must detect markers split across multiple `w:r`/`w:t` elements by Word's formatting engine.

**Acceptance Criteria**:
- The full text of each paragraph is reconstructed by concatenating all `w:t` descendant values.
- Markers fragmented as `$`, `{CONTENT`, `}` across separate `w:t` elements are detected correctly.
- The paragraph containing the fragmented marker is returned as the marker location.

#### FR-13: Structured Result

The library must return a structured `MergeResult` with comprehensive operation details.

**Acceptance Criteria**:
- `success: bool` indicates whether the merge completed without fatal exceptions.
- `outputPath: string` contains the path to the generated file.
- `errors: list<string>` contains non-fatal error messages.
- `warnings: list<string>` contains informational warnings.
- `stats: array<string, int>` contains counters (markers_replaced, images_copied, styles_merged, headers_copied).
- `executionTime: float` contains elapsed time in seconds.

### 2.2 Should Have (P1)

#### FR-14: Drawing Object ID Remapping

Remap `wp:docPr id` attributes to prevent duplicate drawing object IDs when merging documents with images.

#### FR-15: Bookmark ID Remapping

Remap `w:bookmarkStart`/`w:bookmarkEnd` `w:id` attributes to prevent duplicates.

#### FR-16: Source Document Caching

Cache opened ZIP archives and parsed DOMs for source documents when multiple markers reference the same file. Avoid reopening and reparsing the same file N times.

#### FR-17: Uncovered Section Warnings

Emit a warning when a source document has N sections but only M < N are referenced by markers.

#### FR-18: Reprocessing Support

Support using an existing output file as the base for additional merges, enabling multi-pass processing for templates with many markers.

### 2.3 Could Have (P2)

#### FR-19: AbstractNum Deduplication

Deduplicate identical `w:abstractNum` definitions between source and target to reduce `numbering.xml` size. Deferred due to a known bug in the reference implementation where deduplication caused bullets to render as Roman numerals.

#### FR-20: Charts, Diagrams, and OLE Objects

Support copying chart files, diagram data, and embedded OLE objects from source documents. Deferred to v2.

---

## 3. Non-Functional Requirements

### 3.1 Code Quality

| ID | Requirement | Verification |
|---|---|---|
| NFR-01 | PHPStan level 8 with zero errors on `src/` and `tests/` | `composer analyse` |
| NFR-02 | Test coverage >= 90% line coverage | `composer test:coverage` |
| NFR-03 | PSR-12 compliance with zero violations | `composer format:check` |
| NFR-04 | All public methods fully typed (parameters, return types, property types) | PHPStan level 8 |
| NFR-05 | PHPDoc on every class, interface, and public method | Code review |

### 3.2 Compatibility

| ID | Requirement | Verification |
|---|---|---|
| NFR-06 | PHP >= 8.1 | `composer.json` constraint |
| NFR-07 | Zero framework dependencies in `src/` | Dependency audit |
| NFR-08 | Runtime dependencies: `psr/log ^3.0`, ext-zip, ext-dom, ext-xml, ext-mbstring | `composer.json` |
| NFR-09 | Output DOCX opens without repair prompt in Word 2016+ | Manual testing |
| NFR-10 | Output DOCX opens correctly in LibreOffice 7+ | Manual testing |

### 3.3 Security

| ID | Requirement | Verification |
|---|---|---|
| NFR-11 | XXE protection: `LIBXML_NONET` flag on all XML parsing | Code review, unit test |
| NFR-12 | Path traversal prevention: reject `../` and absolute paths in ZIP entries | Code review, unit test |
| NFR-13 | Zip Slip prevention: sanitize all relationship targets from source documents | Code review, unit test |
| NFR-14 | No `@` error suppression operator | PHPStan, code review |
| NFR-15 | Temporary files created with `tempnam()` (not `uniqid()`) | Code review |

### 3.4 Maintainability

| ID | Requirement | Verification |
|---|---|---|
| NFR-16 | Single Responsibility Principle: each class has one reason to change | Architecture review |
| NFR-17 | Dependency Inversion: all domain services depend on interfaces | Architecture review |
| NFR-18 | All classes are `final` | PHPStan, code review |
| NFR-19 | Exception hierarchy rooted at `DocxMergeException` | Code review |

---

## 4. Use Cases

### UC-01: Simple Single-Marker Merge

**Actor**: Developer
**Preconditions**: Template DOCX with one `${CONTENT}` marker and a source DOCX exist on disk.
**Flow**:
1. Developer creates `DocxMerger` instance.
2. Developer calls `merge()` with template path, `['CONTENT' => '/path/to/source.docx']`, and output path.
3. Library validates template and source.
4. Library locates `${CONTENT}` marker in template.
5. Library extracts body content from source.
6. Library copies styles, numbering, media, headers/footers.
7. Library replaces marker with extracted content.
8. Library validates and writes output.
9. Developer receives `MergeResult` with `success = true`.

### UC-02: Multiple Markers with Different Sources

**Actor**: Developer
**Preconditions**: Template with `${INTRO}` and `${BODY}` markers; two source documents.
**Flow**:
1. Developer calls `merge()` with `['INTRO' => 'intro.docx', 'BODY' => 'body.docx']`.
2. Library processes each marker sequentially.
3. Both markers are replaced with their respective source content.
4. Styles and numbering from both sources are merged without conflicts.

### UC-03: Multi-Section Source with Explicit Section Targeting

**Actor**: Developer
**Preconditions**: Template with `${ASSETS}` and `${LIABILITIES}` markers; source DOCX with 2 sections.
**Flow**:
1. Developer creates `MergeDefinition` objects with `sectionIndex: 0` and `sectionIndex: 1`.
2. Library extracts only section 0 for `${ASSETS}` and section 1 for `${LIABILITIES}`.
3. Each marker receives only its designated section content.
4. Section properties (page layout) are applied per section.

### UC-04: Marker Not Found (Strict Mode)

**Actor**: Developer
**Preconditions**: Template without the expected marker; `strictMarkers = true`.
**Flow**:
1. Developer calls `merge()` with a marker that does not exist in the template.
2. Library throws `MarkerNotFoundException`.
3. Temporary files are cleaned up.

### UC-05: Marker Not Found (Non-Strict Mode)

**Actor**: Developer
**Preconditions**: Template without the expected marker; `strictMarkers = false` (default).
**Flow**:
1. Developer calls `merge()` with a marker that does not exist.
2. Library emits a warning and skips the marker.
3. Merge continues with remaining markers.
4. `MergeResult` contains the warning.

### UC-06: Source with Images

**Actor**: Developer
**Preconditions**: Source document contains embedded images.
**Flow**:
1. Library extracts content including image references.
2. Library copies image files from source ZIP to target ZIP with new names.
3. Library remaps `r:embed` attributes in the content.
4. Images appear correctly in the output document.

### UC-07: Source with Headers and Footers

**Actor**: Developer
**Preconditions**: Source document has custom headers and footers.
**Flow**:
1. Library copies header/footer XML files to target ZIP.
2. Library creates local `.rels` files for header/footer images.
3. Library updates `sectPr` references in the merged content.
4. Headers and footers appear in the correct sections of the output.

### UC-08: Fragmented Marker Detection

**Actor**: Developer
**Preconditions**: Template where Word has split `${CONTENT}` across multiple `w:t` elements.
**Flow**:
1. Library reconstructs full paragraph text by concatenating all `w:t` values.
2. Library detects the marker in the reconstructed text.
3. Library replaces the entire paragraph with source content.

### UC-09: Invalid Template

**Actor**: Developer
**Preconditions**: Template file does not exist or is not a valid ZIP.
**Flow**:
1. Developer calls `merge()` with an invalid template path.
2. Library throws `InvalidTemplateException` with a descriptive message.

### UC-10: Invalid Source (Section Index Out of Bounds)

**Actor**: Developer
**Preconditions**: Source document has 2 sections; `MergeDefinition` specifies `sectionIndex: 5`.
**Flow**:
1. Library detects that section index exceeds available sections.
2. Library throws `InvalidSourceException` with details about available section count.

---

## 5. Use Case Diagram

```
                          +------------------+
                          |    Developer     |
                          +--------+---------+
                                   |
                 +-----------------+------------------+
                 |                 |                   |
                 v                 v                   v
        +--------+------+  +------+--------+  +-------+-------+
        | UC-01: Simple |  | UC-02: Multi  |  | UC-03: Multi  |
        | Single Marker |  | Marker Merge  |  | Section Merge |
        +--------+------+  +------+--------+  +-------+-------+
                 |                 |                   |
                 +--------+--------+-------+-----------+
                          |                |
                          v                v
                 +--------+------+  +------+--------+
                 | UC-06: Source |  | UC-07: Source  |
                 | with Images  |  | with H/F      |
                 +--------+------+  +------+--------+
                          |                |
                          v                v
                 +--------+------+  +------+---------+
                 | UC-08: Frag. |  | UC-04/05: Not  |
                 | Marker       |  | Found (S/NS)   |
                 +---------------+  +------+---------+
                                           |
                                    +------+--------+
                                    | UC-09/10:     |
                                    | Invalid Input |
                                    +---------------+

                    <<extends>>
        UC-06, UC-07 extend UC-01/UC-02 (resource copying)
        UC-08 extends UC-01 (marker detection variant)
        UC-04, UC-05 are alternate flows of UC-01
        UC-09, UC-10 are error flows
```

---

## 6. Data Dictionary

| Term | Definition |
|---|---|
| Template | A DOCX file containing `${MARKER}` placeholders to be replaced |
| Source Document | A DOCX file whose body content replaces a marker in the template |
| Marker | A placeholder string matching `${NAME}` in the template's `document.xml` |
| Merge Definition | A DTO specifying the association between a marker name, source path, and optional section index |
| Section | A portion of a DOCX document bounded by `w:sectPr` elements, defining page layout |
| Section Properties (sectPr) | XML element defining page size, margins, orientation, and header/footer references |
| Relationship ID (rId) | A unique identifier linking content elements to their resources (images, hyperlinks, headers) |
| Style ID (styleId) | A unique identifier for a paragraph, character, or table style |
| Numbering ID (numId) | A unique identifier linking a paragraph to a numbering definition |
| Abstract Numbering ID | A unique identifier for a numbering format definition (bullets, numbers, outlines) |
| Content Types | The `[Content_Types].xml` file mapping ZIP parts to MIME types |
