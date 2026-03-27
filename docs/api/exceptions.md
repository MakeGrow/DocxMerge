# Exceptions

> Exception hierarchy for the DocxMerge library.

All exceptions thrown by DocxMerge extend the abstract `DocxMergeException` class, which itself extends PHP's `RuntimeException`. This allows callers to catch all library exceptions with a single `catch (DocxMergeException $e)` block, or to catch specific exception types for fine-grained error handling.

**Namespace**: `DocxMerge\Exception\`

## Hierarchy

```
RuntimeException
  └── DocxMergeException (abstract)
        ├── InvalidTemplateException
        ├── InvalidSourceException
        ├── MarkerNotFoundException
        ├── XmlParseException
        ├── ZipOperationException
        └── MergeException
```

## DocxMergeException

> Abstract base exception for all DocxMerge errors.

```php
abstract class DocxMergeException extends \RuntimeException
```

Not instantiable directly. Use `catch (DocxMergeException $e)` to catch any library exception.

## InvalidTemplateException

> Thrown when the template file is not found, not readable, or not a valid DOCX.

```php
final class InvalidTemplateException extends DocxMergeException
```

**Thrown by**: `DocxMerger::merge()` during template validation.

**Common causes**:
- The template file path does not exist.
- The file is not a valid ZIP/DOCX archive.

## InvalidSourceException

> Thrown when a source file is not found, not readable, not a valid DOCX, or the section index is out of bounds.

```php
final class InvalidSourceException extends DocxMergeException
```

**Thrown by**: The merge pipeline when opening or parsing a source document.

**Common causes**:
- The source file path does not exist.
- The file is not a valid ZIP/DOCX archive.
- The `sectionIndex` in a `MergeDefinition` exceeds the number of sections in the source document.

## MarkerNotFoundException

> Thrown when a marker is not found in the template document.

```php
final class MarkerNotFoundException extends DocxMergeException
```

**Thrown by**: The merge pipeline during marker location.

**Condition**: Only thrown when `MergeOptions::$strictMarkers` is `true`. When strict mode is disabled (the default), missing markers are silently skipped and reported as warnings in `MergeResult::$warnings`.

## XmlParseException

> Thrown when malformed XML is encountered in a document part.

```php
final class XmlParseException extends DocxMergeException
```

**Thrown by**: XML parsing operations throughout the merge pipeline.

**Common causes**:
- A DOCX part (e.g., `word/document.xml`, `word/styles.xml`) contains malformed XML.
- The document was corrupted or manually edited with invalid XML.

## ZipOperationException

> Thrown when a ZIP operation fails (open, read, or write).

```php
final class ZipOperationException extends DocxMergeException
```

**Thrown by**: ZIP read/write operations throughout the merge pipeline.

**Common causes**:
- Unable to open a ZIP archive.
- Unable to read a specific entry from a ZIP archive.
- Unable to write to the output ZIP file (permissions, disk space).

## MergeException

> Thrown for general merge errors not covered by more specific exception types.

```php
final class MergeException extends DocxMergeException
```

**Thrown by**: The merge pipeline for unrecoverable errors.

**Common causes**:
- An unexpected error during any phase of the 13-phase merge pipeline.
- Internal state inconsistencies that prevent the merge from completing.

## Examples

### Catching all library exceptions

```php
use DocxMerge\DocxMerger;
use DocxMerge\Exception\DocxMergeException;

$merger = new DocxMerger();

try {
    $result = $merger->merge($template, $merges, $output);
} catch (DocxMergeException $e) {
    echo "DocxMerge error: {$e->getMessage()}\n";
}
```

### Fine-grained exception handling

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
    echo "Template problem: {$e->getMessage()}\n";
} catch (InvalidSourceException $e) {
    echo "Source file problem: {$e->getMessage()}\n";
} catch (MarkerNotFoundException $e) {
    echo "Missing marker: {$e->getMessage()}\n";
} catch (DocxMergeException $e) {
    echo "Other merge error: {$e->getMessage()}\n";
}
```
