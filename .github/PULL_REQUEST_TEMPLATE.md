## Description

<!-- Clear description of what this PR does and why -->

## Type of Change

- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change
- [ ] Documentation update
- [ ] Refactoring (no functional changes)

## Related Issues

Fixes #
Relates to #

## Changes

### New Files

- `path/to/file.php` -- Description of purpose

### Modified Files

- `path/to/file.php` -- Description of changes

## Testing

- [ ] `composer test` passes
- [ ] `composer analyse` passes (PHPStan level 8)
- [ ] New tests added for new functionality
- [ ] Unit tests use in-memory XML only (no filesystem)
- [ ] Integration tests use real `.docx` fixtures

## Checklist

- [ ] `declare(strict_types=1)` in every file
- [ ] Code follows PSR-12 standards
- [ ] PHPStan level 8 passes with no new errors
- [ ] All classes are `final` with `private` default visibility
- [ ] Used `readonly` properties where applicable
- [ ] DTOs are immutable value objects
- [ ] Exceptions extend `DocxMergeException` hierarchy
- [ ] PHPDoc in English on all new/modified classes and public methods
- [ ] `composer ci` passes (analyse + format:check + test:coverage)
- [ ] No `.project/` planning files committed

## OOXML Conformance

<!-- If this PR modifies XML/ZIP handling, describe conformance considerations:
     rId remapping, w:numId resequencing, [Content_Types].xml, namespace handling.
     State "No XML structure changes" if not applicable. -->

## Notes

<!-- Additional context or areas requiring reviewer attention -->
