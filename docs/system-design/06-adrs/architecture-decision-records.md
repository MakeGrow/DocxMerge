# Architecture Decision Records: DocxMerge

## ADR-001: Stateless Services with MergeContext

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation stored mutable state in 14 instance properties (`imageCounter`, `relationshipIdCounter`, `headerFooterIdMap`, `styleIdCounter`, `numIdCounter`, `abstractNumIdCounter`, `errors`, `warnings`, `relationshipMap`, `headerFooterMap`, `headerFooterCounter`, `globalHeaderFooterMap`, `debugMode`). A `resetState()` method was called at the start of each `merge()`, but it intentionally did not reset `headerFooterCounter` (to prevent filename collisions across calls), creating hidden state leakage between operations (bugs B2-002, B2-003, R-017, R-018).

### Decision

All domain services are stateless. Mutable state for a single merge operation is encapsulated in a `MergeContext` object, created fresh at the start of each `execute()` call. ID counters are managed by a dedicated `IdTracker` initialized from the target document's current state.

### Consequences

- **Positive**: No state leakage between `merge()` calls. Each operation is fully isolated. Services are safely reusable and testable in isolation.
- **Positive**: Eliminates bugs B2-002 (fixed counter base) and B2-003 (headerFooterCounter accumulation).
- **Negative**: Slightly more verbose -- `MergeContext` must be passed to service methods instead of services accessing `$this` properties.
- **Negative**: Cannot reuse previously computed ID bases across calls (minor performance cost of re-scanning on each call).

---

## ADR-002: Explicit Marker-Section Association via MergeDefinition

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation used `detectSequentialMarkers()` with a regex `/^(.+?)_(.+)$/` to automatically detect when multiple markers point to the same source document and associate them with sections by array position. This heuristic produced false positives, false negatives, and silent content duplication (bugs B3-001, B3-002, B3-003, B3-005, B3-006).

### Decision

The caller explicitly specifies the marker-to-section association using `MergeDefinition` with an optional `sectionIndex` field. String values in the `$merges` array are normalized to `MergeDefinition(sectionIndex: null)`, meaning the entire document is used.

### Consequences

- **Positive**: Eliminates the entire class of B3 bugs. No heuristic, no implicit behavior.
- **Positive**: Caller has full control and visibility over what content goes where.
- **Positive**: Invalid section indices are caught early with `InvalidSourceException`.
- **Negative**: Slightly more verbose API for the multi-section use case (caller must create `MergeDefinition` objects instead of just passing strings).
- **Negative**: Caller must know the section count of their source documents. Mitigated by clear error messages when indices are out of bounds.

### Alternatives Considered

- **Improved heuristic with `MARKER#N` convention**: Still implicit, still fragile. Users would need to learn a naming convention.
- **Automatic section detection with warnings**: Complex, ambiguous results. The library cannot reliably guess which section the caller wants.

---

## ADR-003: SHA-256 Content Hash for Style Comparison

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation compared each source style against all target styles using normalized XML string comparison, resulting in O(N*M) complexity where N = source styles and M = target styles (risk R-008). For documents with hundreds of styles, this became a significant performance bottleneck.

### Decision

Normalize style XML (remove `w:styleId`, `w:customStyle`, `w:default`, `w:name`, `w:aliases`, normalize whitespace) and compute SHA-256 hash. Build a hash-to-styleId index for the target. Look up each source style hash in the index for O(1) comparison.

### Consequences

- **Positive**: Reduces complexity from O(N*M) to O(N+M). Significant speedup for style-heavy documents.
- **Positive**: Deterministic -- same styles always produce the same hash.
- **Positive**: Hash index can be reused across multiple source documents in the same merge.
- **Negative**: SHA-256 computation adds small constant overhead per style. Negligible in practice.
- **Negative**: Hash collisions are theoretically possible but cryptographically improbable with SHA-256.

---

## ADR-004: Defense-in-Depth Numbering Strategy

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation's `mergeNumbering()` used `appendChild()` which could violate OOXML ordering requirements (all `w:abstractNum` before all `w:num`). The `renumberAllListsSequentially()` renumbered IDs but did not reorder DOM nodes (bug B2-001). This caused Word to sometimes misrender lists.

### Decision

Implement both ordered insertion in `NumberingMerger` and full DOM reconstruction in `NumberingResequencer`:
1. `NumberingMerger.merge()` uses `insertBefore()` for abstractNum (before first num) and `appendChild()` for num (after all abstractNum).
2. `NumberingResequencer.resequence()` collects all numbering nodes, removes them, renumbers them sequentially, and re-inserts them in guaranteed correct order.

### Consequences

- **Positive**: Guarantees correct OOXML ordering regardless of input state or bugs in earlier processing steps.
- **Positive**: Defense-in-depth: even if the merger has a bug, the resequencer corrects it.
- **Positive**: Sequential IDs (0,1,2... and 1,2,3...) produce clean, minimal numbering.xml.
- **Negative**: The resequencer iterates all numbering nodes and all document references. For very large documents with many numbering definitions, this adds processing time.
- **Negative**: Two components doing related work (insertion ordering + resequencing) -- but each has a distinct responsibility.

---

## ADR-005: Source Document Caching

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation opened and parsed the same source ZIP for every marker that referenced it (bug B3-004). If three markers pointed to `balance.docx`, the file was opened, all XML parts parsed, and styles/numbering imported three times -- causing duplicate styles in the output and wasted CPU.

### Decision

Introduce `SourceDocumentCache` that stores `SourceDocument` (ZipArchive handle + parsed DOMs + section count) indexed by file path. First access opens and parses; subsequent accesses return the cached instance.

### Consequences

- **Positive**: Each source file is opened and parsed exactly once per merge operation.
- **Positive**: Eliminates duplicate style and numbering imports from the same source.
- **Positive**: Significant performance improvement for multi-section documents with many markers.
- **Negative**: Cached DOMs consume memory for the entire merge duration. For many large source files, this increases peak memory usage.
- **Negative**: ZipArchive handles remain open until `SourceDocumentCache.clear()` is called (at end of merge). Acceptable since PHP's ZipArchive handles are lightweight.

---

## ADR-006: DOM Node List Over XML String for Extracted Content

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation serialized extracted content to an XML string, then re-parsed it when inserting into the target DOM. This approach required careful namespace declaration management and could lose namespace information during serialization/re-parsing cycles.

### Decision

`ExtractedContent` carries `list<DOMNode>` instead of an XML string. Nodes are directly passed to `importNode()` for cross-document import, preserving the DOM tree structure without serialization.

### Consequences

- **Positive**: No re-parsing required. Preserves complete DOM structure including namespaces.
- **Positive**: `importNode()` handles namespace reconciliation automatically.
- **Positive**: `IdRemapper` can operate directly on imported DOM nodes without string manipulation.
- **Negative**: Nodes are tied to their owner document until imported. Cannot serialize/cache extracted content independently.
- **Negative**: A temporary XML serialization is still needed for `RelationshipManager.buildMap()` and `NumberingMerger.buildMap()` to filter referenced IDs (implemented as `serializeNodesToXml()`).

---

## ADR-007: Paragraph-Level Marker Reconstruction for Fragmented Markers

**Status**: Accepted
**Date**: 2026-03-12

### Context

Microsoft Word frequently splits text across multiple `w:r`/`w:t` elements when formatting changes, spell-check marks, or revision tracking is applied. A marker like `${CONTENT}` might be stored as `$` in one `w:t`, `{CONTENT` in another, and `}` in a third (risk R-006). The reference implementation only searched individual `w:t` elements, missing fragmented markers.

### Decision

For each `w:p` (paragraph), concatenate the text content of all descendant `w:t` elements to reconstruct the full paragraph text. Apply the marker pattern against this reconstructed text. If a match is found, return the paragraph element.

### Consequences

- **Positive**: Detects markers regardless of how Word has split the text across runs.
- **Positive**: Simple algorithm -- no DOM modification before detection.
- **Positive**: Works for any fragmentation pattern (2 fragments, 10 fragments, any split point).
- **Negative**: Replaces the entire paragraph containing the marker, not just the marker text. This is acceptable because markers are expected to be the sole content of their paragraph.
- **Negative**: Cannot detect markers that span multiple paragraphs (not a realistic scenario for `${MARKER}` patterns).

---

## ADR-008: Defer AbstractNum Deduplication to v2

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation had a complete `findIdenticalAbstractNum()` mechanism that was intentionally disabled (lines 1508-1512) because it caused bullets to render as Roman numerals (risk R-021). The root cause was not identified.

### Decision

Do not implement abstractNum deduplication in v1. The `NumberingResequencer` ensures clean sequential IDs without deduplication. Accept potentially larger `numbering.xml` files as a trade-off for correctness.

### Consequences

- **Positive**: Avoids reintroducing a known bug without understanding its root cause.
- **Positive**: Simpler implementation -- no complex comparison logic for abstract numbering definitions.
- **Positive**: `NumberingResequencer` guarantees valid numbering regardless of duplicates.
- **Negative**: Output `numbering.xml` may contain redundant abstractNum definitions when the same source is used multiple times or when source and target share similar list formats.
- **Negative**: Slightly larger file size, but numbering definitions are small compared to document content.

---

## ADR-009: Framework-Agnostic with PSR-3 Logging

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation was coupled to Laravel through `Illuminate\Support\Facades\Log` (risk R-002) and `Illuminate\Support\Traits\Macroable`. This prevented use as a standalone Composer package.

### Decision

The library depends only on `psr/log ^3.0` for logging. `LoggerInterface` is injected via constructor with `NullLogger` as default. No framework classes anywhere in `src/`.

### Consequences

- **Positive**: Usable in any PHP project (Laravel, Symfony, Slim, vanilla PHP).
- **Positive**: Publishable as a standalone Composer package.
- **Positive**: Testable without framework bootstrapping.
- **Negative**: Users must provide their own logger implementation if they want diagnostic output.
- **Negative**: No access to framework conveniences (service container, facades). Mitigated by the simple constructor-based DI approach.

---

## ADR-010: All Classes Final

**Status**: Accepted
**Date**: 2026-03-12

### Context

Design choice for the library's extension model.

### Decision

All classes are declared `final`. Extension is through interfaces (Dependency Inversion) and composition, not inheritance.

### Consequences

- **Positive**: Prevents fragile base class problems. Internal implementation details cannot be accidentally overridden.
- **Positive**: PHPStan can make stronger inferences about final classes.
- **Positive**: Forces consumers to use the interface-based extension points (inject custom `StyleMergerInterface` implementation, for example).
- **Negative**: Cannot extend a class for quick one-off customizations. Must implement the full interface instead.

---

## ADR-011: Two-Pass numId Remapping with Temporary Offset

**Status**: Accepted (preserved from reference implementation)
**Date**: 2026-03-12

### Context

When remapping numbering IDs, a direct single-pass replacement can cause incorrect results if a new ID matches an old ID that hasn't been processed yet. Example: if `numId=2` maps to `5` and `numId=5` maps to `3`, processing `2->5` first would cause the later `5->3` to incorrectly change the already-remapped value.

### Decision

Preserve the reference implementation's two-pass strategy:
1. Pass 1: Replace all old numIds with temporary values (old + 999000 offset).
2. Pass 2: Replace all temporary values with final new IDs.

### Consequences

- **Positive**: Guarantees correct remapping regardless of ID overlap between old and new values.
- **Positive**: Simple to implement and reason about.
- **Positive**: The 999000 offset is large enough to avoid collisions with any real numId values.
- **Negative**: Two passes over the content nodes instead of one. Acceptable performance cost.

---

## ADR-012: Validation as Warning, Not Blocking

**Status**: Accepted
**Date**: 2026-03-12

### Context

After merging, the library validates document integrity (rIds, numIds, styles, file existence). The question was whether validation failures should prevent output generation.

### Decision

Validation issues are reported as warnings in `MergeResult`, not as blocking errors. The merged document is always written if the merge pipeline completes without fatal exceptions.

### Consequences

- **Positive**: Callers always get an output file, even if it has minor inconsistencies. This matches the reference implementation's behavior.
- **Positive**: Validation warnings help diagnose issues without blocking the workflow.
- **Positive**: Microsoft Word is tolerant of minor inconsistencies and can often repair them.
- **Negative**: Output documents may have issues that only manifest when opened in strict validators. Callers should check `MergeResult.warnings`.
- **Negative**: A "successful" merge with validation warnings is technically not fully correct.

---

## ADR-013: Intermediate Section Properties Preservation

**Status**: Accepted
**Date**: 2026-03-12

### Context

The reference implementation had five related bugs (B1-001 through B1-005) in handling multi-section source documents: debug text in output, removed headers/footers, disabled rId remapping, incorrect XML ordering, and only applying the last section's properties.

### Decision

Implement a three-phase approach:
1. **ContentExtractor**: Preserves intermediate `w:sectPr` in `w:pPr` with no debug artifacts. `w:pPr` is always the first child of `w:p`. Headers/footers are preserved in the XML.
2. **HeaderFooterCopier**: Copies ALL headers/footers from source (including those used by intermediate sections).
3. **SectionPropertiesApplier**: After content insertion, scans intermediate `w:sectPr` and remaps their `headerReference`/`footerReference` rIds using the `HeaderFooterMap`.

### Consequences

- **Positive**: Fixes all five B1 bugs by design.
- **Positive**: Clean separation of concerns: extraction, copying, and remapping are independent phases.
- **Positive**: Each phase can be tested independently.
- **Negative**: More complex than ignoring intermediate sections (the workaround used by the reference implementation).
- **Negative**: Requires the `HeaderFooterCopier` to process ALL source headers/footers, even those that might not be needed (e.g., if the source has sections the caller doesn't use). Acceptable overhead since header/footer files are small.
