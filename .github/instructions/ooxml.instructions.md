---
applyTo: "src/**/*.php"
---

# OOXML / DOCX Code Review Guidelines

## Purpose

These instructions guide code review for PHP code that manipulates DOCX files (Office Open XML / WordprocessingML). A `.docx` is a ZIP archive containing XML parts connected by relationships. Every manipulation must maintain document integrity.

## XXE Prevention

- Always pass `LIBXML_NONET` when loading XML from DOCX parts
- Never call `DOMDocument::loadXML()` without security flags
- Flag any `loadXML()` call missing `LIBXML_NONET` as a critical security issue

```php
// Correct
$dom->loadXML($xmlString, LIBXML_NONET);

// Wrong -- vulnerable to XXE
$dom->loadXML($xmlString);
```

## Namespace Handling

- All OOXML namespaces must be declared on the root element before serialization
- XPath queries must register namespaces via `DOMXPath::registerNamespace()`
- Never hardcode namespace URIs inline -- use class constants

```php
// Correct -- constants for namespace URIs
private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

$xpath->registerNamespace('w', self::NS_W);
```

## ID Remapping

When merging content from a source document into a target, all IDs must be remapped to avoid collisions:

- `rId` values (relationship IDs) -- remap in `.rels` files and all referencing elements
- `w:numId` and `w:abstractNumId` -- remap in `numbering.xml` and paragraph `w:numPr`
- `w:styleId` -- remap in `styles.xml` and all `w:pStyle`/`w:rStyle` references
- `wp:docPr id` -- unique drawing object identifiers
- `w:id` on bookmarks -- unique across all bookmarks

Flag any code that copies content across documents without remapping IDs.

## Content Types

- Every new part added to the ZIP must have an entry in `[Content_Types].xml`
- Missing entries cause Word to silently ignore the part or refuse to open the file
- `Default` entries apply by file extension; `Override` entries target specific parts
- `PartName` in Override entries must start with `/`

Flag any code that adds files to the ZIP without updating `[Content_Types].xml`.

## Relationships

- Every `rId` referenced in document XML must exist in the corresponding `.rels` file
- Every `Target` in a `.rels` file must point to a file that exists in the ZIP
- External targets (hyperlinks) require `TargetMode="External"`
- Structural relationships (styles, settings, fontTable, theme) must never be removed

Flag any code that adds relationships without verifying the target file exists.

## Node Import Across Documents

- Never directly append a node from one `DOMDocument` into another
- Always use `$targetDom->importNode($sourceNode, deep: true)` first
- Verify the imported node type with `instanceof DOMElement` after import

```php
// Correct
$imported = $targetDom->importNode($sourceNode, deep: true);
$targetBody->insertBefore($imported, $referenceNode);

// Wrong -- will throw DOMException
$targetBody->appendChild($sourceNode);
```

## Whitespace Preservation

- Set `xml:space="preserve"` on any `w:t` element with leading or trailing whitespace
- Word silently strips spaces from `w:t` elements without this attribute
- After DOM manipulation, verify `w:t` elements still have correct `xml:space`

```php
if ($value !== ltrim($value) || $value !== rtrim($value)) {
    $textNode->setAttribute('xml:space', 'preserve');
}
```

## Document Structure Integrity

- The last child of `w:body` must always be `w:sectPr` -- if content is appended, move `w:sectPr` to the end
- Intermediate `w:sectPr` elements must be inside `w:pPr`, not direct children of `w:body`
- Table cells (`w:tc`) must contain at least one `w:p` element
- All `w:abstractNum` elements must appear before all `w:num` elements in `numbering.xml`
- `w:docDefaults` must be the first child of `w:styles`

## Numbering Definitions

- When copying numbering definitions, preserve `w:rFonts` in `w:lvl/w:rPr` exactly -- stripping fonts turns bullet characters into meaningless codepoints
- Choose new `w:abstractNumId` values high enough to avoid collisions (e.g., start from 50000)
- Before adding an `abstractNum`, compare its definition with existing ones to avoid duplicates

## Style Conflict Resolution

- Compare styles by `w:styleId` first, then by normalized content hash
- Identical definitions: skip (no copy needed)
- Different definitions with same ID: rename the source style and update all references in imported content
- Track `w:basedOn` and `w:link` references when renaming styles

## ZipArchive Operations

- `ZipArchive::open()` returns `true` on success or an integer error code -- check explicitly
- `ZipArchive::getFromName()` returns `false` on failure -- always check before using the result
- Never use the `@` error-suppression operator on ZIP operations

```php
// Correct
$result = $zip->open($path);
if ($result !== true) {
    throw new ZipOperationException("Failed to open: {$path}");
}

$content = $zip->getFromName($entry);
if ($content === false) {
    throw new ZipOperationException("Entry not found: {$entry}");
}
```

## DOM Serialization

- `DOMDocument::saveXML()` may omit namespace declarations from child elements -- always declare namespaces on the root element
- Use `$dom->saveXML($dom->documentElement)` to serialize without the XML declaration when embedding into another context
- Set `preserveWhiteSpace = true` and `formatOutput = false` to avoid altering document whitespace

## Common Review Red Flags

- `loadXML()` without `LIBXML_NONET`
- Direct node append across `DOMDocument` instances (missing `importNode`)
- Hardcoded `rId` values instead of remapped IDs
- Missing `[Content_Types].xml` update when adding ZIP parts
- `w:t` elements with spaces but no `xml:space="preserve"`
- Content appended after `w:sectPr` in `w:body`
- `@` error suppression on ZIP or DOM operations
