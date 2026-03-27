# Capacity Planning: DocxMerge

## 1. Component Profile

DocxMerge is a **synchronous, single-threaded, in-memory PHP library** that operates on DOCX files (ZIP archives containing XML). It is not a server or daemon -- it runs within the caller's PHP process for the duration of a single `merge()` call.

## 2. Resource Consumption Model

### 2.1 Memory

DocxMerge loads entire DOCX parts into memory as DOM trees. The primary memory consumers are:

| Resource | Memory Estimate | Scaling Factor |
|---|---|---|
| Template `document.xml` DOM | ~5-10x XML file size | Size of template |
| Template `styles.xml` DOM | ~5-10x XML file size | Number of styles in template |
| Template `numbering.xml` DOM | ~5-10x XML file size | Number of numbering definitions |
| Template `rels` DOM | ~5-10x XML file size | Number of relationships |
| Template `content_types` DOM | ~5-10x XML file size | Number of ZIP parts |
| Source `document.xml` DOM (per cached source) | ~5-10x XML file size | Size of source document |
| Source `styles.xml` DOM (per cached source) | ~5-10x XML file size | Number of source styles |
| Source `numbering.xml` DOM (per cached source) | ~5-10x XML file size | Number of source numbering defs |
| Source `rels` DOM (per cached source) | ~5-10x XML file size | Number of source relationships |
| Imported nodes (after importNode) | ~1x source content DOM | Content being merged |
| ZIP archive handles | Minimal (~1KB per handle) | 1 target + N unique sources |
| DTO maps (StyleMap, NumberingMap, etc.) | ~1KB per mapping entry | Number of styles/numberings/rels |

**DOM Expansion Factor**: PHP's DOMDocument typically uses 5-10x the raw XML file size in memory, due to node objects, namespace registrations, and internal structures.

**Estimation Formula**:

```
Memory ≈ (template_xml_total * 8) + (sum_of_unique_sources_xml_total * 8) + overhead

Where:
  template_xml_total = size of document.xml + styles.xml + numbering.xml + rels + content_types
  unique_sources_xml_total = sum of (document.xml + styles.xml + numbering.xml + rels) for each unique source
  overhead = ~2-5 MB for PHP runtime, ZipArchive handles, and temporary strings
```

### 2.2 Disk I/O

| Operation | I/O Pattern | Size |
|---|---|---|
| Read template ZIP | Sequential read | Full template file size |
| Copy to temp file | Sequential write | Full template file size |
| Read source ZIP(s) | Random read (ZIP entries) | Sum of relevant XML parts per source |
| Write media files to target ZIP | Sequential write per file | Sum of image sizes |
| Write header/footer files | Sequential write per file | Small (1-50 KB each) |
| Serialize DOMs to ZIP | Sequential write per part | ~equal to original XML sizes |
| Move temp to output | Rename (same FS) or copy | Full output file size |

### 2.3 CPU

| Operation | Complexity | Dominant Factor |
|---|---|---|
| XML parsing (createDom) | O(N) where N = XML size | Number and size of XML parts |
| XPath queries | O(N) per query | DOM size |
| Style hash comparison | O(S + T) where S=source styles, T=target styles | Number of styles |
| Numbering resequencing | O(A + N + R) where A=abstractNums, N=nums, R=document numId refs | Number of numbering definitions |
| ID remapping in content | O(C * M) where C=content nodes, M=map sizes | Content size and map sizes |
| Content import (importNode) | O(C) where C=content node tree size | Content complexity |

## 3. Scale Limits

### 3.1 Practical Limits per Merge Operation

| Dimension | Recommended Limit | Hard Limit | Bottleneck |
|---|---|---|---|
| Template file size | < 50 MB | ~100 MB (depends on PHP memory_limit) | Memory for DOM trees |
| Source file size (individual) | < 50 MB | ~100 MB | Memory for cached DOM trees |
| Number of markers | < 100 | ~500 | Linear processing time |
| Number of unique source files | < 50 | ~100 | Memory for cached DOMs |
| Markers per source (same file) | < 20 | ~50 | Content extraction per section |
| Sections per source | < 20 | ~50 | Section boundary detection |
| Total styles (template + all sources) | < 1,000 | ~5,000 | Hash index memory, merge time |
| Total numbering definitions | < 500 | ~2,000 | Resequencing time |
| Total images (all sources) | < 500 | ~2,000 | Disk I/O for copy operations |
| Total headers/footers | < 50 | ~200 | File copy and relationship management |
| Output file size | < 100 MB | ~200 MB | ZipArchive write performance |

### 3.2 Memory Limit Guidelines

| Scenario | Estimated Peak Memory | Recommended `memory_limit` |
|---|---|---|
| Small merge (1 marker, <1 MB docs) | ~20 MB | 64 MB |
| Medium merge (5 markers, <10 MB total) | ~80-120 MB | 256 MB |
| Large merge (20 markers, <50 MB total) | ~300-500 MB | 512 MB |
| Very large merge (50+ markers, >50 MB) | ~500 MB - 1 GB | 1 GB+ |

### 3.3 Execution Time Estimates

| Scenario | Estimated Time | Dominant Operation |
|---|---|---|
| 1 marker, simple source (no images) | < 1 second | XML parsing + DOM manipulation |
| 5 markers, sources with images | 2-5 seconds | Image copying + DOM serialization |
| 20 markers, complex sources | 5-15 seconds | DOM manipulation + ID remapping |
| 50+ markers, large documents | 15-60 seconds | Everything scales linearly |

Note: These are rough estimates. Actual performance depends on hardware, PHP version, and document complexity.

## 4. Scaling Characteristics

### 4.1 What Scales Linearly

- Number of markers (each processed sequentially).
- Number of unique source files (each cached after first access).
- Total content size (node import and ID remapping).
- Number of images (file copy operations).

### 4.2 What Does Not Scale Well

- **DOM size in memory**: DOMDocument memory usage grows non-linearly with XML complexity. Very deeply nested documents (many tables within tables) consume disproportionately more memory.
- **XPath queries on large DOMs**: Query time increases with DOM size. The library uses targeted queries (not `//` wildcards on the entire document when possible).
- **Concurrent markers on same source**: While cached, each extraction creates new node lists. Many markers on the same large source multiply node processing.

### 4.3 Not Supported (Out of Scope for v1)

- **Streaming/chunked processing**: The library loads entire DOM trees into memory. Documents that exceed available memory cannot be processed.
- **Parallel processing**: The library is single-threaded. PHP's ZipArchive is not thread-safe.
- **Incremental processing**: Each `merge()` call processes all markers in a single pass. There is no resume capability.

## 5. Operational Recommendations

### 5.1 PHP Configuration

```ini
; For typical usage (< 20 markers, < 50 MB total docs)
memory_limit = 256M
max_execution_time = 120

; For heavy usage (50+ markers, large docs)
memory_limit = 1G
max_execution_time = 300
```

### 5.2 Filesystem

- Use local SSD storage for temp directory (`sys_get_temp_dir()`). Network filesystems significantly impact ZipArchive performance.
- Ensure `sys_get_temp_dir()` has sufficient free space (at least 2x the expected output file size).
- The library creates one temporary file per `merge()` call and cleans it up on completion or failure.

### 5.3 For Web Applications

- Execute merge operations in background workers (queues), not in HTTP request handlers.
- Set appropriate PHP timeouts for the expected merge complexity.
- Monitor memory usage if processing user-uploaded documents of unknown size.
- Validate document sizes before calling `merge()` to prevent OOM situations.

### 5.4 Monitoring Points

| Metric | Source | Alert Threshold |
|---|---|---|
| Execution time | `MergeResult.executionTime` | > 30 seconds |
| Memory peak | `memory_get_peak_usage(true)` | > 80% of `memory_limit` |
| Warnings count | `MergeResult.warnings` | > 0 (investigate) |
| Errors count | `MergeResult.errors` | > 0 (investigate) |
| Validation issues | Warnings with "Validation:" prefix | Any (indicates potential corruption) |

## 6. Known Limitations

1. **No streaming**: Entire document DOMs must fit in memory. This is a fundamental limitation of PHP's DOMDocument.
2. **Single-threaded**: PHP's execution model. Cannot parallelize marker processing.
3. **ZipArchive limitations**: PHP's ZipArchive does not support in-memory ZIP operations; a temp file is always created.
4. **DOM modification overhead**: Each `importNode()` + `insertBefore()` triggers DOM re-indexing. Very large numbers of insertions (thousands of nodes) may exhibit quadratic behavior in extreme cases.
5. **No deduplication of abstractNum**: Disabled in v1 to avoid a known formatting bug. May result in larger `numbering.xml` files when many sources share similar list definitions.

## 7. Future Scalability Improvements (v2+)

| Improvement | Impact | Complexity |
|---|---|---|
| XMLReader-based content extraction | Reduce memory for large source docs | High -- lose DOM manipulation capability |
| Lazy source document loading | Reduce peak memory when many sources | Medium -- defer DOM parsing until needed |
| AbstractNum deduplication | Smaller numbering.xml | Medium -- requires fixing the bullet/roman numeral bug |
| Parallel image copying | Reduce I/O time for image-heavy merges | Low -- images are independent |
| Incremental DOM serialization | Reduce peak memory during write | High -- ZipArchive API limitation |
