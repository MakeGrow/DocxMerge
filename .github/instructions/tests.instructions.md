---
applyTo: "tests/**/*.php"
---

# Pest Testing Guidelines

## Purpose

These instructions guide code review for all test files using Pest PHP v3.
Tests must be clear, focused, and maintainable.
Every test file must begin with `<?php` followed by `declare(strict_types=1);`.

## Test Structure

- Use `it()` for all test cases — never use `test()` or PHPUnit class-based syntax
- Test descriptions must start with a lowercase verb and read as a sentence
- One assertion focus per test — split tests that verify unrelated behaviors
- Group related tests using `describe()` blocks

```php
// Avoid
test('Test user creation', function () { ... });
it('works', function () { ... });

// Prefer
describe('UserService', function () {
    it('creates a user with valid data', function () { ... });
    it('throws an exception when email is missing', function () { ... });
});
```

## Assertions

- Prefer Pest expectation API (`expect()`) over PHPUnit assertions
- Chain expectations when asserting multiple properties of the same subject
- Use `toBeInstanceOf()`, `toBeNull()`, `toBeTrue()`, `toBeFalse()` instead of generic `toBe()`

```php
// Avoid
$this->assertInstanceOf(User::class, $user);
$this->assertEquals('John', $user->name);
$this->assertTrue($user->isActive());

// Prefer
expect($user)
    ->toBeInstanceOf(User::class)
    ->name->toBe('John')
    ->isActive()->toBeTrue();
```

## Exception Testing

- Use `->toThrow()` expectation for exception assertions
- Always assert both the exception class and the message when the message is part of the public API

```php
// Avoid
try {
    $service->process('');
    $this->fail('Expected exception');
} catch (ValidationException $e) {
    $this->assertEquals('Input is required.', $e->getMessage());
}

// Prefer
expect(fn () => $service->process(''))
    ->toThrow(ValidationException::class, 'Input is required.');
```

## Datasets

- Use `with()` for parameterized tests instead of duplicating test cases
- Use named datasets for readability when datasets are not self-explanatory
- Keep datasets close to the test that uses them

```php
it('rejects invalid emails', function (string $email) {
    expect(fn () => new Email($email))
        ->toThrow(InvalidEmailException::class);
})->with([
    'missing @' => ['invalid-email'],
    'missing domain' => ['user@'],
    'empty string' => [''],
]);
```

## Setup and Teardown

- Use `beforeEach()` for shared setup within a `describe()` block
- Avoid deep nesting — if setup is complex, extract a helper function
- Never share mutable state across tests

## Mocking

- Prefer fakes and stubs over mocks when possible
- Use Mockery only when verifying interactions is necessary
- Always call `Mockery::close()` or rely on Pest's built-in teardown

```php
// Prefer a fake
$gateway = new FakePaymentGateway();
$service = new PaymentService($gateway);

expect($service->charge(1000))->toBeTrue();
expect($gateway->totalCharges())->toBe(1000);
```

## Coverage and Completeness

- Every public method in the package must have at least one test
- Test the happy path first, then edge cases and error conditions
- Include boundary value tests for numeric inputs and string lengths
- Test with empty inputs, null values, and unexpected types where applicable

## Unit vs Integration Tests

- **Unit tests** (`tests/Unit/`): No filesystem access, no real `.docx` files, in-memory XML strings only. Use `createDomFromXml()` helper for DOM creation.
- **Integration tests** (`tests/Integration/`): Use real `.docx` fixtures from `tests/Integration/Fixtures/`. Output to `sys_get_temp_dir()`. Always `unlink()` in `afterEach()`.
- Unit test directories mirror `src/` structure: `src/Style/StyleMerger.php` -> `tests/Unit/Style/StyleMergerTest.php`

## Global Helpers (defined in `tests/Pest.php`)

- `fixture(string $name): string` -- returns path to integration fixture (integration tests only)
- `createDomFromXml(string $xml): DOMDocument` -- parses XML with `LIBXML_NONET`, matching production behavior
- `createXpathWithNamespaces(DOMDocument $dom): DOMXPath` -- registers all OOXML namespaces

Do not duplicate these helpers. Flag any test that redefines them.

## Naming Conventions

- Test files must mirror the source structure and end with `Test.php`
- Test descriptions in English, imperative outcome form: `it('throws when the template does not exist')`
- All closures passed to `it()`, `beforeEach()`, etc. must declare return type `void`

## What to Avoid

- Never test framework internals or third-party library behavior (ZipArchive, DOMDocument)
- Never use `sleep()` or real HTTP calls
- Never access the filesystem in unit tests -- use `createDomFromXml()` with inline XML
- Never leave `dump()`, `dd()`, `var_dump()`, or `ray()` in test files
- Never skip tests without a clear reason documented in the skip message
- Never use `fixture()` in unit tests -- it accesses the filesystem
