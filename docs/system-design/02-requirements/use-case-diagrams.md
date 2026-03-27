# Use Case Diagrams: DocxMerge

## 1. Primary Use Cases

```
+================================================================+
|                        DocxMerge Library                        |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |                     Merge Operations                       |  |
|  |                                                            |  |
|  |  +----------------------+   +-------------------------+   |  |
|  |  | UC-01: Simple Merge  |   | UC-02: Multi-Marker     |   |  |
|  |  | (single marker,      |   | Merge (N markers,       |   |  |
|  |  |  single source)      |   |  N sources)             |   |  |
|  |  +----------+-----------+   +------------+------------+   |  |
|  |             |                            |                |  |
|  |             +-------------+--------------+                |  |
|  |                           |                               |  |
|  |             +-------------v--------------+                |  |
|  |             | UC-03: Multi-Section Merge  |                |  |
|  |             | (explicit sectionIndex)      |                |  |
|  |             +-----------------------------+                |  |
|  +-----------------------------------------------------------+  |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |                  Resource Handling                         |  |
|  |                                                            |  |
|  |  +----------------------+   +-------------------------+   |  |
|  |  | UC-06: Copy Images   |   | UC-07: Copy Headers/    |   |  |
|  |  | from Source          |   | Footers from Source      |   |  |
|  |  +----------------------+   +-------------------------+   |  |
|  +-----------------------------------------------------------+  |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |                   Edge Cases                               |  |
|  |                                                            |  |
|  |  +----------------------+   +-------------------------+   |  |
|  |  | UC-08: Fragmented    |   | UC-04: Marker Not Found |   |  |
|  |  | Marker Detection     |   | (strict mode -> throw)  |   |  |
|  |  +----------------------+   +-------------------------+   |  |
|  |                             +-------------------------+   |  |
|  |                             | UC-05: Marker Not Found |   |  |
|  |                             | (non-strict -> warning) |   |  |
|  |                             +-------------------------+   |  |
|  +-----------------------------------------------------------+  |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  |                  Error Handling                            |  |
|  |                                                            |  |
|  |  +----------------------+   +-------------------------+   |  |
|  |  | UC-09: Invalid       |   | UC-10: Invalid Source   |   |  |
|  |  | Template             |   | (section out of bounds) |   |  |
|  |  +----------------------+   +-------------------------+   |  |
|  +-----------------------------------------------------------+  |
+================================================================+
           ^
           |
    +------+------+
    |  Developer   |
    | (API Caller) |
    +-------------+
```

## 2. Merge Pipeline Sequence (UC-01 Detail)

```
Developer          DocxMerger         MergeOrchestrator      Services
    |                   |                    |                    |
    |  merge(template,  |                    |                    |
    |   merges, output) |                    |                    |
    |------------------>|                    |                    |
    |                   | validateTemplate() |                    |
    |                   |-------+            |                    |
    |                   |<------+            |                    |
    |                   |                    |                    |
    |                   | normalizeDefinitions()                  |
    |                   |-------+            |                    |
    |                   |<------+            |                    |
    |                   |                    |                    |
    |                   | execute(template,  |                    |
    |                   |  defs, output, opt)|                    |
    |                   |------------------->|                    |
    |                   |                    | createWorkingCopy()|
    |                   |                    |-------+            |
    |                   |                    |<------+            |
    |                   |                    |                    |
    |                   |                    | loadDOMs()         |
    |                   |                    |-------+            |
    |                   |                    |<------+            |
    |                   |                    |                    |
    |                   |                    | initIdTracker()    |
    |                   |                    |-------+            |
    |                   |                    |<------+            |
    |                   |                    |                    |
    |                   |                    | processDefinition()|
    |                   |                    |------------------->|
    |                   |                    |                    | locate marker
    |                   |                    |                    | extract content
    |                   |                    |                    | build ID maps
    |                   |                    |                    | copy resources
    |                   |                    |                    | merge defs
    |                   |                    |                    | import nodes
    |                   |                    |                    | remap IDs
    |                   |                    |                    | apply sectPr
    |                   |                    |                    | remove marker
    |                   |                    |<-------------------|
    |                   |                    |                    |
    |                   |                    | resequence()       |
    |                   |                    | preserveSpaces()   |
    |                   |                    | serializeDOMs()    |
    |                   |                    | updateContentTypes()|
    |                   |                    | validate()         |
    |                   |                    | moveToOutput()     |
    |                   |                    |                    |
    |                   |    MergeResult     |                    |
    |                   |<-------------------|                    |
    |    MergeResult    |                    |                    |
    |<------------------|                    |                    |
```

## 3. Multi-Section Merge (UC-03 Detail)

```
Developer                    DocxMerger
    |                            |
    |  merge(template, [         |
    |    'ASSETS' => MergeDef(   |
    |      source: 'balance.docx'|
    |      sectionIndex: 0),     |
    |    'LIABS' => MergeDef(    |
    |      source: 'balance.docx'|
    |      sectionIndex: 1),     |
    |  ], output)                |
    |--------------------------->|
    |                            |
    |      MergeOrchestrator     |
    |         processes:         |
    |                            |
    |  1. Locate ${ASSETS}       |
    |  2. Cache balance.docx     |
    |  3. Extract section 0 only |
    |  4. Replace ${ASSETS}      |
    |                            |
    |  5. Locate ${LIABS}        |
    |  6. Reuse cached doc       |
    |  7. Extract section 1 only |
    |  8. Replace ${LIABS}       |
    |                            |
    |       MergeResult          |
    |<---------------------------|
```

## 4. Error Flow (UC-09/UC-10 Detail)

```
Developer          DocxMerger              Exception
    |                   |                      |
    |  merge(invalid,   |                      |
    |   merges, output) |                      |
    |------------------>|                      |
    |                   | validateTemplate()   |
    |                   |---+                  |
    |                   |   | file not found   |
    |                   |   | or invalid ZIP   |
    |                   |<--+                  |
    |                   |                      |
    |                   | throw                |
    |                   |--------------------->|
    |                   |  InvalidTemplate     |
    |<--------------------- Exception          |
    |                   |                      |
    |                   |                      |
    |  merge(template,  |                      |
    |   ['M' => MergeDef|                      |
    |    (source,        |                      |
    |     sectionIndex:  |                      |
    |     99)], output)  |                      |
    |------------------>|                      |
    |                   | processDefinition()  |
    |                   | extract(dom, 99)     |
    |                   |---+                  |
    |                   |   | index > sections |
    |                   |<--+                  |
    |                   | throw                |
    |                   |--------------------->|
    |                   |  InvalidSource       |
    |<--------------------- Exception          |
```

## 5. Resource Copying Sub-Flow (UC-06/UC-07 Detail)

```
MergeOrchestrator     MediaCopier      HeaderFooterCopier     Target ZIP
       |                   |                   |                   |
       | copy(src, tgt,    |                   |                   |
       |  relMap, tracker) |                   |                   |
       |------------------>|                   |                   |
       |                   | for each image    |                   |
       |                   | rel in map:       |                   |
       |                   |   read from src   |                   |
       |                   |   rename file     |                   |
       |                   |   write to tgt--->|------------------>|
       |                   |                   |                   |
       |   imagesCopied    |                   |                   |
       |<------------------|                   |                   |
       |                   |                   |                   |
       | copy(src, tgt,    |                   |                   |
       |  relsDom, tracker)|                   |                   |
       |-------------------------------------->|                   |
       |                   |                   | for each h/f:     |
       |                   |                   |   read XML + rels |
       |                   |                   |   copy images     |
       |                   |                   |   create .rels    |
       |                   |                   |   write XML------>|
       |                   |                   |   add rel to DOM  |
       |                   |                   |                   |
       |   HeaderFooterMap |                   |                   |
       |<--------------------------------------|                   |
```
