# DocxMerge

[![CI](https://github.com/mkgrow/docx-merge/actions/workflows/ci.yml/badge.svg)](https://github.com/mkgrow/docx-merge/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg)](https://www.php.net/)

A framework-agnostic PHP 8.2+ Composer library that merges DOCX documents by substituting `${MARKER}` placeholders in a template with content extracted from source `.docx` files. Supports multi-section extraction, style deduplication, numbering resequencing, media copying, and header/footer merging -- all while maintaining valid OOXML document structure.

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | >= 8.2 |
| ext-zip | * |
| ext-dom | * |
| ext-xml | * |
| ext-mbstring | * |
| psr/log | ^3.0 |

## Installation

```bash
composer require mkgrow/docx-merge
```

## Quick Start

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
    echo "Merged {$result->stats['markers_replaced']} markers in {$result->executionTime}s\n";
}
```

The template document must contain `${MARKER}` placeholders (e.g., `${INTRODUCTION}`, `${CONCLUSION}`) as paragraph text. Each placeholder is replaced with the full body content of the corresponding source DOCX file.

## Usage

### Simple single-marker merge

```php
use DocxMerge\DocxMerger;

$merger = new DocxMerger();

$result = $merger->merge(
    templatePath: '/templates/report.docx',
    merges: [
        'CONTENT' => '/sources/content.docx',
    ],
    outputPath: '/output/report.docx',
);
```

### Multiple markers with different sources

```php
$result = $merger->merge(
    templatePath: '/templates/contract.docx',
    merges: [
        'HEADER'      => '/sources/header.docx',
        'TERMS'       => '/sources/terms.docx',
        'APPENDIX_A'  => '/sources/appendix-a.docx',
        'APPENDIX_B'  => '/sources/appendix-b.docx',
    ],
    outputPath: '/output/contract.docx',
);
```

### Section targeting with MergeDefinition

When a source document has multiple sections, use `MergeDefinition` to extract a specific section by its zero-based index:

```php
use DocxMerge\DocxMerger;
use DocxMerge\Dto\MergeDefinition;

$merger = new DocxMerger();

$result = $merger->merge(
    templatePath: '/templates/book.docx',
    merges: [
        'CHAPTER_ONE' => new MergeDefinition(
            markerName: 'CHAPTER_ONE',
            sourcePath: '/sources/chapters.docx',
            sectionIndex: 0,
        ),
        'CHAPTER_TWO' => new MergeDefinition(
            markerName: 'CHAPTER_TWO',
            sourcePath: '/sources/chapters.docx',
            sectionIndex: 1,
        ),
    ],
    outputPath: '/output/book.docx',
);
```

### Configuring MergeOptions

#### Strict marker mode

Throws `MarkerNotFoundException` when a marker is not found in the template, instead of silently skipping it:

```php
use DocxMerge\Dto\MergeOptions;

$options = new MergeOptions(strictMarkers: true);

$result = $merger->merge($template, $merges, $output, $options);
```

#### Custom marker pattern

Override the default `${MARKER}` pattern with a custom regex. The first capture group must contain the marker name:

```php
use DocxMerge\Dto\MergeOptions;

// Match {{MARKER}} instead of ${MARKER}
$options = new MergeOptions(
    markerPattern: '/\{\{([A-Z_][A-Z0-9_]*)\}\}/',
);
```

#### Reprocessing mode

Merge additional markers into a previously generated output file:

```php
use DocxMerge\Dto\MergeOptions;

// First pass
$merger->merge($template, ['HEADER' => $header], $output);

// Second pass: merge more markers into the same output
$options = new MergeOptions(isReprocessing: true);
$merger->merge($template, ['FOOTER' => $footer], $output, $options);
```

### Handling the MergeResult

`MergeResult` provides structured feedback about the merge operation:

```php
$result = $merger->merge($template, $merges, $output);

// Check success
if (!$result->success) {
    foreach ($result->errors as $error) {
        error_log("Merge error: {$error}");
    }
}

// Inspect warnings
foreach ($result->warnings as $warning) {
    echo "Warning: {$warning}\n";
}

// Access processing stats
echo "Markers replaced: {$result->stats['markers_replaced']}\n";
echo "Execution time: {$result->executionTime}s\n";
echo "Output: {$result->outputPath}\n";
```

### Error handling with typed exceptions

All exceptions extend `DocxMergeException`, allowing both broad and fine-grained error handling:

```php
use DocxMerge\DocxMerger;
use DocxMerge\Exception\InvalidTemplateException;
use DocxMerge\Exception\InvalidSourceException;
use DocxMerge\Exception\MarkerNotFoundException;
use DocxMerge\Exception\DocxMergeException;

$merger = new DocxMerger();

try {
    $result = $merger->merge($template, $merges, $output);
} catch (InvalidTemplateException $e) {
    // Template file not found or not a valid DOCX
} catch (InvalidSourceException $e) {
    // Source file invalid or section index out of bounds
} catch (MarkerNotFoundException $e) {
    // Marker not found (only when strictMarkers is true)
} catch (DocxMergeException $e) {
    // Any other library error (XmlParseException, ZipOperationException, MergeException)
}
```

### Using a PSR-3 logger

Pass any PSR-3 compatible logger to receive diagnostic output:

```php
use DocxMerge\DocxMerger;
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger Your application's logger */
$merger = new DocxMerger(logger: $logger);

$result = $merger->merge($template, $merges, $output);
```

## API Reference

| Class | Description | Documentation |
| --- | --- | --- |
| `DocxMerger` | Public facade with `merge()` method | [docs/api/docx-merger.md](docs/api/docx-merger.md) |
| `MergeDefinition` | DTO for marker-to-source binding with section targeting | [docs/api/merge-definition.md](docs/api/merge-definition.md) |
| `MergeOptions` | DTO for merge configuration (pattern, strict mode, reprocessing) | [docs/api/merge-options.md](docs/api/merge-options.md) |
| `MergeResult` | DTO for merge results (success, stats, errors, warnings) | [docs/api/merge-result.md](docs/api/merge-result.md) |
| Exceptions | 6 typed exceptions extending `DocxMergeException` | [docs/api/exceptions.md](docs/api/exceptions.md) |

## Architecture Overview

DocxMerge follows a 4-layer architecture with strict downward dependency flow:

| Layer | Components | Responsibility |
| --- | --- | --- |
| **1. Public API** | `DocxMerger`, DTOs | Entry point, input validation, normalization |
| **2. Orchestration** | `MergeOrchestrator`, `MergeContext` | 13-phase pipeline coordination, per-operation state |
| **3. Domain Services** | 12 services (e.g., `MarkerLocator`, `StyleMerger`, `IdRemapper`) | Individual merge concerns (styles, numbering, media, relationships) |
| **4. Infrastructure** | `XmlHelper`, `ZipHelper`, `IdTracker`, `SourceDocumentCache` | XML/ZIP utilities, ID tracking, document caching |

All domain services depend on interfaces and are stateless (per ADR-001). Per-operation mutable state is encapsulated in `MergeContext`.

For complete architecture documentation, see [docs/system-design/](docs/system-design/).

## Development

### Prerequisites

- PHP 8.2+ with extensions: zip, dom, xml, mbstring
- Composer 2.x

### Setup

```bash
git clone https://github.com/mkgrow/docx-merge.git
cd docx-merge
composer install
```

### Commands

| Command | Description |
| --- | --- |
| `composer test` | Run the full Pest v3 test suite |
| `composer test --filter=Name` | Run a specific describe block or test |
| `composer test:coverage` | Run tests with coverage gate (minimum 90%) |
| `composer analyse` | Run PHPStan level 8 on `src/` and `tests/` |
| `composer format` | Apply PSR-12 formatting via php-cs-fixer |
| `composer format:check` | Check PSR-12 compliance without modifying files |
| `composer ci` | Full quality gate: analyse + format:check + test:coverage |

### Testing

The test suite uses Pest v3 with 182 tests and 357 assertions, achieving 95.2% line coverage.

- **Unit tests** (`tests/Unit/`): Pure in-memory XML tests with no filesystem access.
- **Integration tests** (`tests/Integration/`): Real `.docx` fixture-based tests using `tests/Integration/Fixtures/`.

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage
```

### Quality Gate

Before submitting changes, ensure the full CI pipeline passes:

```bash
composer ci
```

This runs PHPStan level 8, PSR-12 format check, and test coverage gate (minimum 90%) in sequence.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed version history.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
