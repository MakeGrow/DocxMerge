# MergeOptions

> Configuration options for a merge operation.

`MergeOptions` is an immutable DTO that controls marker pattern matching, strict mode, and reprocessing behavior. All properties have sensible defaults, so creating an instance with no arguments produces a working configuration.

**Namespace**: `DocxMerge\Dto\MergeOptions`

## Constructor

```php
public function __construct(
    public readonly string $markerPattern = '/\$\{([A-Z_][A-Z0-9_]*)\}/',
    public readonly bool $strictMarkers = false,
    public readonly bool $isReprocessing = false,
)
```

## Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `$markerPattern` | `string` | `/\$\{([A-Z_][A-Z0-9_]*)\}/` | Regex pattern for matching markers in the template. The first capture group must contain the marker name. |
| `$strictMarkers` | `bool` | `false` | When `true`, a `MarkerNotFoundException` is thrown if any marker defined in the merges array is not found in the template. When `false`, missing markers are silently skipped. |
| `$isReprocessing` | `bool` | `false` | When `true`, uses the existing output file as the base for new merges, enabling incremental merge operations. |

## Examples

### Default options

```php
use DocxMerge\Dto\MergeOptions;

// All defaults: standard marker pattern, non-strict, no reprocessing
$options = new MergeOptions();
```

### Strict marker mode

```php
use DocxMerge\Dto\MergeOptions;

// Throws MarkerNotFoundException if any marker is not found in the template
$options = new MergeOptions(strictMarkers: true);
```

### Reprocessing mode

```php
use DocxMerge\Dto\MergeOptions;

// First pass: merge some markers
$options1 = new MergeOptions();
$merger->merge($template, ['HEADER' => $header], $output, $options1);

// Second pass: merge remaining markers into the output from the first pass
$options2 = new MergeOptions(isReprocessing: true);
$merger->merge($template, ['FOOTER' => $footer], $output, $options2);
```

### Custom marker pattern

```php
use DocxMerge\Dto\MergeOptions;

// Use a different marker syntax, e.g., {{MARKER}}
$options = new MergeOptions(
    markerPattern: '/\{\{([A-Z_][A-Z0-9_]*)\}\}/',
);
```
