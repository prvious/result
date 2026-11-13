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

Check the result, handle the error with an early return, then read the value:

```php
$result = divide(10, 2);

if ($result->isErr()) {
    return "Could not divide: {$result->error()}";
}

return "Result: {$result->unwrap()}";
```

Always check `isErr()` or `isOk()` before calling `unwrap()` or `error()`. Reading the wrong side throws a [`Panic`](#about-panic).

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

That's the whole API. There are no callback methods; results are handled with plain control flow.

PHPStan narrows the variant after `isOk()` or `isErr()`, including after an early return:

```php
if ($result->isErr()) {
    return $result->error();
}

$value = $result->unwrap();
```

## Propagating and translating errors

To pass an error up unchanged, return the `Err` itself. Give each fallible step its own check:

```php
/**
 * @return Result<string, string>
 */
function halfOfQuotient(float $dividend, float $divisor): Result
{
    $quotient = divide($dividend, $divisor);

    if ($quotient->isErr()) {
        return $quotient;
    }

    $half = divide($quotient->unwrap(), 2);

    if ($half->isErr()) {
        return $half;
    }

    return Result::ok(number_format($half->unwrap(), 2));
}
```

An `Err` has no value, so it fits any `Result` with the same error type.

To translate an error into the vocabulary of the current operation, wrap it inside the check:

```php
if ($result->isErr()) {
    return Result::err(['message' => $result->error()]);
}
```

## About `Panic`

`Panic` means the Result API was used incorrectly: `unwrap()` was called on an `Err`, or `error()` was called on an `Ok`.

It extends `LogicException`, exposes the wrongly accessed payload through `$panic->payload`, and is not a third Result variant. Do not catch it for business flow—use `isOk()` or `isErr()` instead.

Static analysis does not flag an unchecked `unwrap()`, so test the error path of your code as well as the success path.

If an incorrectly unwrapped `Err` contains a `Throwable`, that throwable is preserved as `$panic->getPrevious()`.

## Results are not exception replacement

Use `Err` for outcomes the caller is expected to handle: a duplicate email, an expired invite, a declined payment, or a missing domain object.

Unexpected database, network, filesystem, and provider failures should normally remain exceptions, so reporting, retries, and transaction rollbacks continue to work normally.

## Frameworks and data objects

The package has no Laravel integration, service provider, or configuration. A Laravel action can return a `Result` in the same way, and an `Ok` may contain a Spatie Data object without making `spatie/laravel-data` a package dependency.

Applications running PHP 8.5 may add `#[\NoDiscard]` to methods returning `Result` to warn when the outcome is ignored. The package itself supports PHP 8.4, so it does not apply that attribute.

## Upgrading from 0.1

0.2 removes `match()`, `map()`, `mapError()`, and `andThen()`. Replace each one with a check and an early return:

- `match()`: handle the error in an `if ($result->isErr())` block that returns, then handle the value.
- `map()`: after the check, return `Result::ok(...)` with the transformed `$result->unwrap()`.
- `mapError()`: inside the check, return `Result::err(...)` with the transformed `$result->error()`.
- `andThen()`: after the check, return the next step called with `$result->unwrap()`.

The replacements only use methods that exist in both versions, so rewrite your code first and then change your constraint to `^0.2`. Projects using [Laravel Boost](https://github.com/laravel/boost) get a `prvious-result-upgrade-0-2` skill that walks AI agents through the rewrite.

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
