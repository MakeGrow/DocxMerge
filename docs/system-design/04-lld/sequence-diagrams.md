# Sequence Diagrams: DocxMerge Critical Flows

## 1. Main Merge Pipeline

This is the primary flow for a complete merge operation from API call to output file.

```
Caller           DocxMerger          Orchestrator         Services           ZIP/Filesystem
  |                   |                    |                    |                    |
  | merge(tpl, merges,|                    |                    |                    |
  |  output, options)  |                    |                    |                    |
  |------------------->|                    |                    |                    |
  |                   |                    |                    |                    |
  |                   | validateTemplate() |                    |                    |
  |                   |----+               |                    |                    |
  |                   |    | ZipArchive    |                    |                    |
  |                   |    | ::open(RDONLY)|                    |                    |
  |                   |    |-------------->|                    |                    |
  |                   |    |    result     |                    |                    |
  |                   |    |<-------------|                    |                    |
  |                   |<---+               |                    |                    |
  |                   |                    |                    |                    |
  |                   | normalizeDefinitions()                  |                    |
  |                   |----+               |                    |                    |
  |                   |    | string->       |                    |                    |
  |                   |    | MergeDefinition|                    |                    |
  |                   |<---+               |                    |                    |
  |                   |                    |                    |                    |
  |                   | execute(tpl, defs, |                    |                    |
  |                   |  output, options)  |                    |                    |
  |                   |------------------->|                    |                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 1]          |                    |
  |                   |                    | createWorkingCopy()|                    |
  |                   |                    |---------------------------------------->|
  |                   |                    |                    |   tempnam() + copy |
  |                   |                    |<----------------------------------------|
  |                   |                    |                    |                    |
  |                   |                    | [Phase 2]          |                    |
  |                   |                    | ZipArchive::open() |                    |
  |                   |                    |---------------------------------------->|
  |                   |                    |<----------------------------------------|
  |                   |                    |                    |                    |
  |                   |                    | [Phase 3]          |                    |
  |                   |                    | loadDOMs()         |                    |
  |                   |                    |---->XmlHelper      |                    |
  |                   |                    |     createDom() x5 |                    |
  |                   |                    |<----               |                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 4]          |                    |
  |                   |                    | IdTracker::        |                    |
  |                   |                    |  initFromTarget()  |                    |
  |                   |                    |---->IdTracker      |                    |
  |                   |                    |     scan DOMs+ZIP  |                    |
  |                   |                    |<----               |                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 5]          |                    |
  |                   |                    | new MergeContext() |                    |
  |                   |                    |----+               |                    |
  |                   |                    |<---+               |                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 6]          |                    |
  |                   |                    | for each def:     |                    |
  |                   |                    |  processDefinition|                    |
  |                   |                    |  (see Diagram 2)  |                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 7]          |                    |
  |                   |                    |------------------->|                    |
  |                   |                    | resequence(num,doc)|                    |
  |                   |                    | preserveSpaces(doc)|                    |
  |                   |                    |<-------------------|                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 8-9]        |                    |
  |                   |                    | serializeDom() x5  |                    |
  |                   |                    |---------------------------------------->|
  |                   |                    | updateContentTypes |                    |
  |                   |                    |---------------------------------------->|
  |                   |                    |<----------------------------------------|
  |                   |                    |                    |                    |
  |                   |                    | [Phase 10]         |                    |
  |                   |                    |------------------->|                    |
  |                   |                    | validate(context)  |                    |
  |                   |                    |<-------------------|                    |
  |                   |                    |                    |                    |
  |                   |                    | [Phase 11-12]      |                    |
  |                   |                    | close ZIP          |                    |
  |                   |                    | rename(temp,output)|                    |
  |                   |                    |---------------------------------------->|
  |                   |                    |<----------------------------------------|
  |                   |                    |                    |                    |
  |                   |   MergeResult      |                    |                    |
  |                   |<-------------------|                    |                    |
  |   MergeResult     |                    |                    |                    |
  |<------------------|                    |                    |                    |
```

## 2. Per-Definition Processing Pipeline

This flow details steps 7a through 7j for processing a single `MergeDefinition`.

```
Orchestrator     MarkerLocator   SourceCache    ContentExtractor   Map Builders
     |                |              |                |                |
     | [7a] locate()  |              |                |                |
     |--------------->|              |                |                |
     |                | XPath query  |                |                |
     |                | //w:p        |                |                |
     |                | concatenate  |                |                |
     |                | w:t values   |                |                |
     |                | match pattern|                |                |
     |  MarkerLocation|              |                |                |
     |<---------------|              |                |                |
     |                |              |                |                |
     | [7b] get(path) |              |                |                |
     |------------------------------>|                |                |
     |                |              | (first call:   |                |
     |                |              |  open ZIP,     |                |
     |                |              |  parse DOMs,   |                |
     |                |              |  count sections)|               |
     |  SourceDocument|              |                |                |
     |<------------------------------|                |                |
     |                |              |                |                |
     | [7c] extract() |              |                |                |
     |---------------------------------------------->|                |
     |                |              |                | iterate w:body |
     |                |              |                | identify sectPr|
     |                |              |                | wrap intermed. |
     |                |              |                | exclude final  |
     |  ExtractedContent             |                |                |
     |<----------------------------------------------|                |
     |                |              |                |                |
     | [7d] buildMap() (x3)          |                |                |
     |--------------------------------------------------------------->|
     |                |              |                |   StyleMerger  |
     |                |              |                |   .buildMap()  |
     |                |              |                |                |
     |                |              |                |   Numbering    |
     |                |              |                |   Merger       |
     |                |              |                |   .buildMap()  |
     |                |              |                |                |
     |                |              |                |   Relationship |
     |                |              |                |   Manager      |
     |                |              |                |   .buildMap()  |
     |  StyleMap, NumberingMap, RelationshipMap        |                |
     |<---------------------------------------------------------------|


Orchestrator     MediaCopier    HFCopier     StyleMerger   NumMerger  RelManager
     |                |             |              |            |          |
     | [7e] copy()    |             |              |            |          |
     |--------------->|             |              |            |          |
     |  imagesCopied  |             |              |            |          |
     |<---------------|             |              |            |          |
     |                |             |              |            |          |
     | [7e] copy()    |             |              |            |          |
     |----------------------------->|              |            |          |
     |  HeaderFooterMap             |              |            |          |
     |<-----------------------------|              |            |          |
     |                |             |              |            |          |
     | [7f] merge()   |             |              |            |          |
     |-------------------------------------------->|            |          |
     |  stylesMerged  |             |              |            |          |
     |<--------------------------------------------|            |          |
     |                |             |              |            |          |
     | [7f] merge()   |             |              |            |          |
     |----------------------------------------------------->|          |
     |<-----------------------------------------------------|          |
     |                |             |              |            |          |
     | [7f] addRelationships()      |              |            |          |
     |---------------------------------------------------------------->|
     |<----------------------------------------------------------------|


Orchestrator        DOM Operations      IdRemapper      SectPrApplier
     |                    |                  |                |
     | [7g] importNode()  |                  |                |
     |  + insertBefore()  |                  |                |
     |  for each node     |                  |                |
     |------------------->|                  |                |
     |  insertedNodes[]   |                  |                |
     |<-------------------|                  |                |
     |                    |                  |                |
     | [7h] remap()       |                  |                |
     |-------------------------------------->|                |
     |                    |  rId, styles,    |                |
     |                    |  numId (2-pass), |                |
     |                    |  docPr, bookmark |                |
     |<--------------------------------------|                |
     |                    |                  |                |
     | [7i] apply()       |                  |                |
     |--------------------------------------------------->|
     |                    |  update intermed.|                |
     |                    |  sectPr rIds     |                |
     |                    |  apply final     |                |
     |                    |  sectPr          |                |
     |<---------------------------------------------------|
     |                    |                  |                |
     | [7j] removeChild() |                  |                |
     |  (marker paragraph)|                  |                |
     |------------------->|                  |                |
     |<-------------------|                  |                |
```

## 3. Style Merge Flow

```
StyleMerger
     |
     | buildMap(sourceDom, targetDom)
     |
     | [1] Index target styles by content hash
     |     for each w:style in target:
     |       normalize XML (remove styleId, name, etc.)
     |       hash = sha256(normalized)
     |       hashIndex[hash] = styleId
     |
     | [2] Map source styles
     |     for each w:style in source:
     |       normalize XML
     |       hash = sha256(normalized)
     |       |
     |       +-- hash in hashIndex?
     |       |     YES -> reuseExisting = true, newId = hashIndex[hash]
     |       |     NO  -> styleId conflicts with target?
     |       |              YES -> newId = nextStyleId() (1000, 1001, ...)
     |       |              NO  -> newId = originalId
     |
     | return StyleMap
     |
     | merge(targetDom, styleMap)
     |
     | [3] Create DocumentFragment for batch insert
     | [4] For each non-reused style:
     |       clone node, update styleId
     |       update basedOn, next, link references
     |       append to fragment
     | [5] Append fragment to styles root
     |
     | return count of imported styles
```

## 4. Numbering Resequencing Flow

```
NumberingResequencer
     |
     | resequence(numberingDom, documentDom)
     |
     | [1] Collect existing elements
     |     abstractNums = XPath //w:abstractNum -> list
     |     nums = XPath //w:num -> list
     |     parent = w:numbering element
     |
     | [2] Remove all from DOM
     |     for each abstractNum: parent.removeChild()
     |     for each num: parent.removeChild()
     |
     | [3] Build renumbering maps
     |     oldAbstractId -> newAbstractId (0, 1, 2, ...)
     |     oldNumId -> newNumId (1, 2, 3, ...)
     |
     | [4] Re-insert in correct order
     |     for each abstractNum (sorted):
     |       update w:abstractNumId to new value
     |       parent.appendChild(abstractNum)
     |
     |     for each num (sorted):
     |       update w:numId to new value
     |       update w:abstractNumId ref using map
     |       parent.appendChild(num)
     |
     | [5] Update document.xml references
     |     for each w:numPr/w:numId in documentDom:
     |       oldVal = w:val
     |       newVal = numMap[oldVal]
     |       update w:val
```

## 5. Two-Pass numId Remapping Flow

```
IdRemapper
     |
     | remap numIds in content nodes
     |
     | Problem: oldId=2 maps to newId=5, but oldId=5 maps to newId=3.
     |          If we process sequentially, changing 2->5 first means
     |          when we later process 5->3, we would incorrectly change
     |          the already-remapped value.
     |
     | Solution: Two-pass with temporary offset (999000)
     |
     | Pass 1: old -> temporary
     |   numId=2 -> numId=999002 (2 + 999000)
     |   numId=5 -> numId=999005 (5 + 999000)
     |
     | State after Pass 1:
     |   Content has numId values: 999002, 999005
     |   No collisions possible (999000+ range is unused)
     |
     | Pass 2: temporary -> final
     |   numId=999002 -> numId=5 (map[2] = 5)
     |   numId=999005 -> numId=3 (map[5] = 3)
     |
     | Final state:
     |   Original 2 -> 5 (correct)
     |   Original 5 -> 3 (correct)
```

## 6. Error Handling Flow

```
Orchestrator                                    Exception
     |                                              |
     | try {                                        |
     |   createWorkingCopy()                        |
     |   open ZIP                                   |
     |   load DOMs                                  |
     |   init IdTracker                             |
     |   create MergeContext                        |
     |                                              |
     |   for each definition:                       |
     |     processDefinition()                      |
     |       |                                      |
     |       +-- marker not found?                  |
     |       |     strictMarkers=true:              |
     |       |       throw MarkerNotFoundException->|
     |       |     strictMarkers=false:             |
     |       |       addWarning, continue           |
     |       |                                      |
     |       +-- source invalid?                    |
     |       |     throw InvalidSourceException --->|
     |       |                                      |
     |       +-- XML malformed?                     |
     |             throw XmlParseException -------->|
     |                                              |
     |   resequence, serialize, validate            |
     |   close ZIP                                  |
     |   moveToOutput                               |
     |   return MergeResult(success: true)          |
     |                                              |
     | } catch (Throwable $e) {                     |
     |   close ZIP if opened                        |
     |   delete temp file if exists                 |
     |                                              |
     |   DocxMergeException subtype?                |
     |     YES -> re-throw as-is                    |
     |     NO  -> wrap in MergeException, re-throw  |
     | }                                            |
```

## 7. Source Document Caching Flow

```
Orchestrator     SourceDocumentCache        ZipArchive       XmlHelper
     |                   |                      |                |
     | get("report.docx")|                      |                |
     |------------------>|                      |                |
     |                   | path in cache?       |                |
     |                   |----+                 |                |
     |                   |    | NO (first call) |                |
     |                   |<---+                 |                |
     |                   |                      |                |
     |                   | open(path)           |                |
     |                   |--------------------->|                |
     |                   |   ZipArchive         |                |
     |                   |<---------------------|                |
     |                   |                      |                |
     |                   | readEntry() x4       |                |
     |                   |--------------------->|                |
     |                   |   XML strings        |                |
     |                   |<---------------------|                |
     |                   |                      |                |
     |                   | createDom() x4       |                |
     |                   |-------------------------------------->|
     |                   |   DOMDocuments       |                |
     |                   |<--------------------------------------|
     |                   |                      |                |
     |                   | countSections()      |                |
     |                   |----+                 |                |
     |                   |<---+                 |                |
     |                   |                      |                |
     |                   | cache[path] = SourceDocument          |
     |  SourceDocument   |                      |                |
     |<------------------|                      |                |
     |                   |                      |                |
     | ... later ...     |                      |                |
     |                   |                      |                |
     | get("report.docx")|                      |                |
     |------------------>|                      |                |
     |                   | path in cache?       |                |
     |                   |----+                 |                |
     |                   |    | YES (cached)    |                |
     |                   |<---+                 |                |
     |  SourceDocument   |                      |                |
     |<------ (same)-----|                      |                |
```
