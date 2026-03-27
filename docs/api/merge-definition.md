# MergeDefinition

> Defines a merge operation linking a marker to a source document section.

`MergeDefinition` is an immutable DTO that binds a marker name to a source DOCX file path, with optional section targeting. When `sectionIndex` is `null`, the entire source document body is used. When specified, only the content from that zero-based section is extracted.

**Namespace**: `DocxMerge\Dto\MergeDefinition`

## Constructor

```php
public function __construct(
    public readonly string $markerName,
    public readonly string $sourcePath,
    public readonly ?int $sectionIndex = null,
)
```

## Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `$markerName` | `string` | -- | The marker name without delimiters (e.g., `'CONTENT'`, not `'${CONTENT}'`). |
| `$sourcePath` | `string` | -- | Absolute path to the source DOCX file. |
| `$sectionIndex` | `?int` | `null` | Zero-based section index to extract, or `null` for the entire document. |

## Examples

### Full document merge

```php
use DocxMerge\Dto\MergeDefinition;

// Use the entire source document to replace ${INTRODUCTION}
$definition = new MergeDefinition(
    markerName: 'INTRODUCTION',
    sourcePath: '/path/to/intro.docx',
);
```

### Section-targeted merge

```php
use DocxMerge\Dto\MergeDefinition;

// Use only the second section (index 1) from the source document
$definition = new MergeDefinition(
    markerName: 'CHAPTER_TWO',
    sourcePath: '/path/to/chapters.docx',
    sectionIndex: 1,
);
```

### Shorthand via DocxMerger

When passing a string value in the `$merges` array to `DocxMerger::merge()`, it is automatically converted to a `MergeDefinition` with `sectionIndex=null`:

```php
// These two are equivalent:
$merges = ['CONTENT' => '/path/to/content.docx'];

$merges = ['CONTENT' => new MergeDefinition(
    markerName: 'CONTENT',
    sourcePath: '/path/to/content.docx',
    sectionIndex: null,
)];
```
