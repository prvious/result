# Result

[![CI](https://img.shields.io/github/actions/workflow/status/prvious/result/ci.yml?branch=main&style=flat-square&label=CI)](https://github.com/prvious/result/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://packagist.org/packages/prvious/result)
[![License](https://img.shields.io/github/license/prvious/result?style=flat-square)](LICENSE.md)

A tiny, typed way to return either a value or an expected error—without turning normal control flow into exceptions.

```text
Result<TValue, TError>
├── Ok<TValue>
└── Err<TError>
```

PHP 8.4+. No runtime dependencies. Framework agnostic. Friendly to PHPStan and other analyzers that understand PHPDoc generics.

## Installation

```shell
composer require prvious/result
```

## Basic usage

```php
<?php

declare(strict_types=1);

use Prvious\Result\Result;

/**
 * @return Result<float, string>
 */
function divide(float $dividend, float $divisor): Result
{
    if ($divisor === 0.0) {
        return Result::err('Cannot divide by zero.');
    }

    return Result::ok($dividend / $divisor);
}
```

The return type describes both possible outcomes: a `float` when the operation succeeds, or a `string` when it cannot be completed.

Use `match()` to handle both:

```php
$output = divide(10, 2)->match(
    ok: fn (float $value): string => "Result: {$value}",
    err: fn (string $error): string => "...",
);
```

Exactly one callback runs, and its value is returned directly—it is not wrapped in another `Result`.

## Creating results

```php
$ok = Result::ok('created');
$err = Result::err('Email is already registered.');
```

Both variants accept any PHP value, including objects, enums, arrays, scalars, `Throwable` instances, and `null`.

## API at a glance

| Method | On `Ok` | On `Err` |
| --- | --- | --- |
| `isOk()` | `true` | `false` |
| `isErr()` | `false` | `true` |
| `unwrap()` | Returns the value | Throws `Panic` |
| `error()` | Throws `Panic` | Returns the error |
| `match($ok, $err)` | Calls `$ok` | Calls `$err` |
| `map($callback)` | Transforms the value | Preserves the error |
| `mapError($callback)` | Preserves the value | Transforms the error |
| `andThen($callback)` | Runs the next `Result` operation | Short-circuits |

PHPStan narrows the variant after `isOk()` or `isErr()`:

```php
if ($result->isOk()) {
    $value = $result->unwrap();
}

if ($result->isErr()) {
    $error = $result->error();
}
```

## Transforming and chaining

Use `map()` when the callback returns a plain value:

```php
$result = divide(10, 2)->map(
    fn (float $value): string => number_format($value, 2),
);
```

Use `mapError()` to translate an error into the vocabulary of the current operation:

```php
$result = divide(10, 0)->mapError(
    fn (string $error): array => ['message' => $error],
);
```

This changes `Result<float, string>` into `Result<float, array{message: string}>` without touching an existing `Ok`.

Use `andThen()` when the next callback already returns a `Result`:

```php
$result = divide(100, 5)
    ->andThen(
        fn (float $value): Result => divide($value, 2),
    );
```

The first `Err` stops the chain. `andThen()` returns the callback's result directly, so it never creates a nested `Result<Result<...>>`.

## About `Panic`

`Panic` means the Result API was used incorrectly: `unwrap()` was called on an `Err`, or `error()` was called on an `Ok`.

It extends `LogicException`, exposes the wrongly accessed payload through `$panic->payload`, and is not a third Result variant. Do not catch it for business flow—use `match()`, `isOk()`, or `isErr()` instead.

If an incorrectly unwrapped `Err` contains a `Throwable`, that throwable is preserved as `$panic->getPrevious()`.

## Results are not exception replacement

Use `Err` for outcomes the caller is expected to handle: a duplicate email, an expired invite, a declined payment, or a missing domain object.

Unexpected database, network, filesystem, and provider failures should normally remain exceptions. This package never catches exceptions thrown inside `match()`, `map()`, `mapError()`, or `andThen()`, so reporting, retries, and transaction rollbacks continue to work normally.

## Frameworks and data objects

The package has no Laravel integration, service provider, or configuration. A Laravel action can return a `Result` in the same way, and an `Ok` may contain a Spatie Data object without making `spatie/laravel-data` a package dependency.

Applications running PHP 8.5 may add `#[\NoDiscard]` to methods returning `Result` to warn when the outcome is ignored. The package itself supports PHP 8.4, so it does not apply that attribute.

## Development

```shell
composer install
composer check
composer test:coverage
composer fix
```

`composer check` runs Composer validation, formatting, linting, static analysis, Rector, and the test suite. Coverage is enforced at 100 percent.

## License

Result is open-source software licensed under the [MIT License](LICENSE.md).
