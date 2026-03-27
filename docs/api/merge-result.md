# MergeResult

> Structured result of a merge operation.

`MergeResult` is an immutable DTO returned by `DocxMerger::merge()`. It contains the success status, output file path, accumulated errors and warnings, processing statistics, and execution time.

**Namespace**: `DocxMerge\Dto\MergeResult`

## Constructor

```php
public function __construct(
    public readonly bool $success,
    public readonly string $outputPath,
    public readonly array $errors,
    public readonly array $warnings,
    public readonly array $stats,
    public readonly float $executionTime,
)
```

## Properties

| Property | Type | Description |
|---|---|---|
| `$success` | `bool` | Whether the merge completed without fatal errors. |
| `$outputPath` | `string` | Absolute path to the generated output DOCX file. |
| `$errors` | `list<string>` | Non-fatal errors accumulated during the merge operation. |
| `$warnings` | `list<string>` | Informational warnings (e.g., unused markers, skipped resources). |
| `$stats` | `array<string, int>` | Processing counters such as `markers_replaced`, `images_copied`, `styles_merged`, etc. |
| `$executionTime` | `float` | Total execution time in seconds. |

## Examples

### Checking merge result

```php
use DocxMerge\DocxMerger;

$merger = new DocxMerger();
$result = $merger->merge($template, $merges, $output);

if ($result->success) {
    echo "Output written to: {$result->outputPath}\n";
    echo "Execution time: {$result->executionTime}s\n";
    echo "Markers replaced: {$result->stats['markers_replaced']}\n";
} else {
    foreach ($result->errors as $error) {
        echo "Error: {$error}\n";
    }
}
```

### Inspecting warnings

```php
if (count($result->warnings) > 0) {
    foreach ($result->warnings as $warning) {
        echo "Warning: {$warning}\n";
    }
}
```

### Accessing statistics

```php
// Available stats depend on the merge operation, typical keys include:
// markers_replaced, images_copied, styles_merged, numberings_merged, etc.
foreach ($result->stats as $key => $value) {
    echo "{$key}: {$value}\n";
}
```
