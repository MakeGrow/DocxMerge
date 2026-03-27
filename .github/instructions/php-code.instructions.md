---
applyTo: "**/*.php"
---

# PHP Code Review Guidelines

## Purpose

These instructions guide code review for all PHP files in this repository.
This is a framework-agnostic PHP 8.1+ library (`DocxMerge\`) following PSR-12 and modern PHP practices.
No framework coupling -- no Laravel, Symfony, or any framework class in `src/`.

## Strict Typing

- Every PHP file must declare `declare(strict_types=1)` on the first line after `<?php`
- All method parameters must have type hints
- All method return types must be declared explicitly
- Never use `mixed` unless truly necessary — prefer union types

## Naming Conventions

- Classes and enums: `PascalCase`
- Methods and variables: `camelCase`
- Constants and enum cases: `UPPER_SNAKE_CASE`
- Boolean methods should start with `is`, `has`, `can`, `should`

```php
// Avoid
function check($val): mixed { ... }

// Prefer
function isValid(string $value): bool { ... }
```

## Code Style (PSR-12)

- Use 4 spaces for indentation, never tabs
- Opening braces on the same line for control structures, next line for classes/methods
- One blank line before return statements when the method has more than one statement
- No trailing whitespace
- Single blank line at end of file

## Modern PHP Patterns

- Use `readonly` properties where applicable (PHP 8.1+)
- Use enums instead of class constants for fixed sets of values
- Use named arguments when calling methods with multiple optional parameters
- Prefer `match` over `switch`
- Use first-class callable syntax (`$this->method(...)`) when passing callbacks
- Use constructor promotion for simple DTOs and value objects

```php
// Avoid
class Config
{
    private string $name;
    private int $timeout;

    public function __construct(string $name, int $timeout)
    {
        $this->name = $name;
        $this->timeout = $timeout;
    }
}

// Prefer
class Config
{
    public function __construct(
        public readonly string $name,
        public readonly int $timeout,
    ) {}
}
```

## Class Design

- All classes must be declared `final` (the only exception is `DocxMergeException`, which is `abstract`)
- Default visibility is `private` -- use `public` only for interface implementations and API surface
- Services that perform loggable operations must accept `Psr\Log\LoggerInterface` via constructor, defaulting to `NullLogger`

## Error Handling

- Throw specific exceptions, never generic `\Exception` or `\RuntimeException`
- All package exceptions must extend `DocxMerge\Exception\DocxMergeException`
- Use the specific exception for the failure domain: `InvalidTemplateException`, `InvalidSourceException`, `MarkerNotFoundException`, `XmlParseException`, `ZipOperationException`, `MergeException`
- Never silently catch and ignore exceptions
- Never use the `@` error-suppression operator
- Use early returns to reduce nesting

```php
// Avoid
function process(string $input): string
{
    if (! empty($input)) {
        if (strlen($input) > 3) {
            return strtoupper($input);
        } else {
            throw new \Exception('Too short');
        }
    } else {
        throw new \Exception('Empty');
    }
}

// Prefer
function process(string $input): string
{
    if (empty($input)) {
        throw new EmptyInputException('Input must not be empty.');
    }

    if (strlen($input) <= 3) {
        throw new InputTooShortException('Input must be longer than 3 characters.');
    }

    return strtoupper($input);
}
```

## Immutability and Side Effects

- Prefer immutable value objects with `readonly` properties
- Methods that transform state should return a new instance instead of mutating
- Avoid static mutable state

## Dependency Management

- Type-hint interfaces instead of concrete implementations
- Inject dependencies via constructor, never use service locators
- Keep constructors focused — if a class needs more than 4 dependencies, consider splitting it

## Security

- Never trust user input — validate and sanitize before use
- Never concatenate raw values into SQL queries
- Never expose stack traces, internal paths, or credentials in exceptions or logs
- Never hardcode secrets, API keys, or tokens

## Documentation

- Every class must have a PHPDoc block describing its responsibility
- Every public and protected method must have a PHPDoc block
- `@param` descriptions must explain meaning and constraints, not just repeat the type
- `@return` always present except for `void`/`never` methods
- `@throws` for every exception that can propagate out of the method
- Inline comments explain WHY, not WHAT
- All PHPDoc and comments in English

```php
/**
 * Locates marker paragraphs in a template document.
 *
 * Handles markers split across multiple w:r/w:t elements by
 * reconstructing the full text of each paragraph.
 *
 * @param DOMDocument $dom The template document DOM.
 * @param string $markerName The marker name without delimiters.
 *
 * @return MarkerLocation|null Null when the marker is not found.
 *
 * @throws XmlParseException If the DOM structure is invalid.
 */
public function locate(DOMDocument $dom, string $markerName): ?MarkerLocation
```

## Package-Specific

- All classes `final` -- composition over inheritance
- Namespace: `DocxMerge\` maps to `src/`, `DocxMerge\Tests\` maps to `tests/`
- Runtime dependencies: `psr/log ^3.0` only -- no framework classes
- Follow semantic versioning -- breaking changes to the public API require a major version bump
- Quality gate before commit: `composer ci` (PHPStan level 8 + PSR-12 + test coverage >= 90%)
