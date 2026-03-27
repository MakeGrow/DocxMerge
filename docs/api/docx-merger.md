# DocxMerger

> Public facade and composition root for the DocxMerge library.

`DocxMerger` is the single entry point for merging source DOCX documents into a template by replacing `${MARKER}` placeholders. It validates the template, normalizes caller inputs, instantiates all internal services, and delegates the merge pipeline to the orchestrator.

**Namespace**: `DocxMerge\DocxMerger`

## Constructor

```php
public function __construct(
    ?LoggerInterface $logger = null,
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$logger` | `?LoggerInterface` | `null` | PSR-3 logger for diagnostic output. When `null`, a `NullLogger` is used. |

## Methods

### merge

> Merges source documents into a template by replacing markers.

```php
public function merge(
    string $templatePath,
    array $merges,
    string $outputPath,
    ?MergeOptions $options = null,
): MergeResult
```

**Parameters**:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$templatePath` | `string` | -- | Absolute path to the template DOCX file. |
| `$merges` | `array<string, string\|MergeDefinition>` | -- | Map of marker name to source path (string) or `MergeDefinition`. String values are automatically normalized to `MergeDefinition` with `sectionIndex=null`. |
| `$outputPath` | `string` | -- | Absolute path for the output DOCX file. |
| `$options` | `?MergeOptions` | `null` | Optional merge configuration. When `null`, defaults to `MergeOptions` defaults. |

**Returns**: `MergeResult` -- Structured result with success flag, stats, errors, warnings, and execution time.

**Throws**:

| Exception | Condition |
|---|---|
| `InvalidTemplateException` | The template does not exist or is not a valid DOCX/ZIP. |
| `InvalidSourceException` | A source file is not found, not readable, or not a valid DOCX. |
| `MarkerNotFoundException` | A marker is not found in the template and `strictMarkers` is enabled. |
| `MergeException` | An unrecoverable error occurs during the merge pipeline. |

## Examples

### Simple merge with string paths

```php
use DocxMerge\DocxMerger;

$merger = new DocxMerger();

$result = $merger->merge(
    templatePath: '/path/to/template.docx',
    merges: [
        'INTRODUCTION' => '/path/to/intro.docx',
        'CONCLUSION'   => '/path/to/conclusion.docx',
    ],
    outputPath: '/path/to/output.docx',
);

if ($result->success) {
    echo "Merge completed in {$result->executionTime}s";
}
```

### Merge with MergeDefinition for section targeting

```php
use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeDefinition;

$merger = new DocxMerger();

$result = $merger->merge(
    templatePath: '/path/to/template.docx',
    merges: [
        'CHAPTER_ONE' => new MergeDefinition(
            markerName: 'CHAPTER_ONE',
            sourcePath: '/path/to/chapters.docx',
            sectionIndex: 0,
        ),
        'CHAPTER_TWO' => new MergeDefinition(
            markerName: 'CHAPTER_TWO',
            sourcePath: '/path/to/chapters.docx',
            sectionIndex: 1,
        ),
    ],
    outputPath: '/path/to/output.docx',
);
```

### Merge with custom options and logging

```php
use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeOptions;
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger */
$merger = new DocxMerger(logger: $logger);

$options = new MergeOptions(
    strictMarkers: true,
    markerPattern: '/\$\{([A-Z_][A-Z0-9_]*)\}/',
);

$result = $merger->merge(
    templatePath: '/path/to/template.docx',
    merges: ['CONTENT' => '/path/to/content.docx'],
    outputPath: '/path/to/output.docx',
    options: $options,
);
```
