---
applyTo: "**/*.php"
---

# PHPStan Code Quality Guidelines

## Purpose

This project uses PHPStan at level 8 for static analysis.
Code must pass PHPStan without baseline ignores for new code.
Run: `composer analyse` (analyses both `src/` and `tests/`).

## Type Safety

- Never use `@var` annotations to silence PHPStan — fix the actual type instead
- Never use `@phpstan-ignore-next-line` without a comment explaining why it is unavoidable
- Avoid `mixed` — narrow types with assertions, instanceof checks, or generics
- All arrays must be typed with PHPStan array shapes or generic syntax

```php
// Avoid
/** @var array $config */
$config = json_decode($json, true);

// Prefer
/** @var array{host: string, port: int, timeout?: int} $config */
$config = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
```

## Generics and Collections

- Use `@template` for generic classes and methods
- Type collections with `array<TKey, TValue>` or `list<T>`, never `array` alone
- Use `@param` and `@return` with generic types when signatures cannot express them natively

```php
// Avoid
/** @return array */
public function getItems(): array { ... }

// Prefer
/** @return list<Item> */
public function getItems(): array { ... }
```

## Null Safety

- Never suppress nullable warnings — handle null explicitly
- Use null coalescing (`??`) or early return to narrow nullable types
- Avoid chained method calls on potentially null values without null checks

```php
// Avoid
$name = $user->getProfile()->getName();

// Prefer
$profile = $user->getProfile();

if ($profile === null) {
    throw new ProfileNotFoundException();
}

$name = $profile->getName();
```

## Return Types

- Never return `void` from a method that is documented as returning a value
- Avoid union return types with more than 3 members — consider a result object instead
- Never use `false` as an error return value — throw an exception

```php
// Avoid
public function find(int $id): User|false { ... }

// Prefer
/** @throws UserNotFoundException */
public function find(int $id): User { ... }
```

## PHPDoc Standards

- Only add PHPDoc when it provides type information beyond PHP native types
- Use PHPStan-specific annotations (`@phpstan-param`, `@phpstan-return`) when PHPDoc types conflict with IDE support
- Always type closure parameters in `@param` when passing callables

```php
// Avoid — redundant
/** @param string $name */
public function setName(string $name): void { ... }

// Prefer — adds generic type info not expressible natively
/** @param callable(Item): bool $filter */
public function filterItems(callable $filter): array { ... }
```

## Strict Comparisons

- Always use strict comparisons (`===`, `!==`) — never loose (`==`, `!=`)
- Use `in_array()` with `true` as the third parameter
- Use `array_key_exists()` instead of `isset()` when checking for key presence with potentially null values

## Class Design

- Avoid magic methods (`__get`, `__set`, `__call`) — they bypass static analysis
- Avoid dynamic properties — declare all properties explicitly
- Final classes must not have `@method` annotations for non-existent methods

## Baseline Rules

- New code must not add entries to the PHPStan baseline
- When fixing existing code, remove related baseline entries
- Never increase the number of ignored errors in `phpstan-baseline.neon`

## Common PHPStan Pitfalls to Flag

- Using `empty()` on typed variables — it hides type information from PHPStan
- Returning `null` from methods typed as non-nullable
- Passing `array<mixed>` to parameters expecting typed arrays
- Using `compact()` or `extract()` — they break static analysis
- Using `assert()` for type narrowing in production code — prefer explicit checks
