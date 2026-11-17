---
name: prvious-result-development
description: Use when writing, reviewing, or refactoring PHP code with the prvious/result package. Covers creating typed Ok and Err outcomes, declaring Result generics for PHPStan, checking results with isOk() and isErr() before reading them, propagating and translating errors with guard clauses, handling Panic correctly, testing result-based code, and using Result in Laravel actions, controllers, jobs, and services. Trigger for Prvious\Result, Result::ok(), Result::err(), isOk(), isErr(), unwrap(), and error().
---

# Prvious Result Development

Use `prvious/result` for expected, caller-actionable failures while keeping successful and failed return types explicit. The package requires PHP 8.4+, has no runtime dependencies, and does not provide Laravel configuration, a facade, a service provider, or container bindings.

The whole API is `Result::ok()`, `Result::err()`, `isOk()`, `isErr()`, `unwrap()`, and `error()`. There are no callback methods: results are handled with plain control flow.

## Create Typed Results

Import `Prvious\Result\Result` and construct outcomes through its factories. Do not instantiate `Ok` or `Err` directly; their constructors are protected.

```php
use Prvious\Result\Result;

/**
 * @return Result<User, RegistrationError>
 */
function registerUser(array $attributes): Result
{
    if (User::query()->where('email', $attributes['email'])->exists()) {
        return Result::err(RegistrationError::EmailTaken);
    }

    return Result::ok(User::query()->create($attributes));
}
```

Always declare both generic types in PHPDoc when a function or method returns the abstract `Result` type:

```php
/**
 * @return Result<Order, CheckoutError>
 */
public function checkout(Cart $cart): Result
```

Prefer precise domain objects, enums, array shapes, or scalar types for `TValue` and `TError`. The factories accept any value, including `null`, but broad `mixed` types weaken static analysis.

## Choose Expected Errors Deliberately

Return `Err` for outcomes callers are expected to handle, such as a duplicate email, expired invitation, declined payment, or missing domain object.

Allow unexpected database, filesystem, network, and provider failures to remain exceptions unless the application has an explicit recovery policy. Do not catch every `Throwable` and convert it to `Err`.

## Check Before You Read

Assign the result to a variable, handle the `Err` with an early return, then read the value on the success path:

```php
$result = $registerUser($attributes);

if ($result->isErr()) {
    return back()->withErrors([
        'email' => match ($result->error()) {
            RegistrationError::EmailTaken => 'That email is already registered.',
        },
    ]);
}

return redirect()->route('users.show', $result->unwrap());
```

Rules:

- Never call `unwrap()` on a result that has not been checked with `isErr()` or `isOk()`. An unchecked `unwrap()` passes static analysis but throws `Panic` the first time the expected error happens.
- Only call `error()` inside an `isErr()` branch (or after `isOk()` returned `false`).
- Prefer `isErr()` with an early return over `if/else`. Use `if/else` or a ternary only when both branches must produce a value and execution continues afterwards:

```php
$label = $result->isOk() ? $result->unwrap()->number : 'Unavailable';
```

PHPStan narrows the variant after either check, including after an early return.

## Propagate, Translate, and Sequence Errors

To pass an error up unchanged, return the narrowed `Err` itself. It satisfies any `Result` with the same error type, whatever the value type:

```php
/**
 * @return Result<OrderData, OrderLookupError>
 */
public function summary(int $id): Result
{
    $result = $this->loadOrder($id);

    if ($result->isErr()) {
        return $result;
    }

    return Result::ok(OrderData::from($result->unwrap()));
}
```

To expose a different error type, translate inside the guard:

```php
if ($result->isErr()) {
    return Result::err(ApiError::from($result->error()));
}
```

To run several fallible steps in sequence, give each step its own variable and its own check:

```php
/**
 * @return Result<Receipt, CheckoutError|PaymentError>
 */
function checkout(Cart $cart): Result
{
    $validated = validateCart($cart);

    if ($validated->isErr()) {
        return $validated;
    }

    return chargeCart($validated->unwrap());
}
```

Do not wrap an existing result with `Result::ok()`; that creates a nested `Result<Result<...>, ...>`. Return it directly.

## Treat Panic as Misuse

`Prvious\Result\Panic` is a `LogicException` raised by `unwrap()` on `Err` or `error()` on `Ok`. It is not a third outcome and must not be used for business flow.

Do not catch `Panic` to recover from expected failures. Branch with `isOk()` or `isErr()` instead. When an incorrectly unwrapped `Err` contains a `Throwable`, `Panic` exposes it through both `$panic->payload` and `$panic->getPrevious()` for diagnosis.

## Test Both Variants

Cover the successful and expected-error paths. Testing the error path is what catches an unchecked `unwrap()` in projects without static analysis. Assert the variant before accessing its payload.

```php
use Prvious\Result\Err;
use Prvious\Result\Ok;

it('registers a user', function (): void {
    $result = registerUser(validAttributes());

    expect($result)->toBeInstanceOf(Ok::class)
        ->and($result->unwrap())->toBeInstanceOf(User::class);
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'person@example.com']);

    $result = registerUser(validAttributes(email: 'person@example.com'));

    expect($result)->toBeInstanceOf(Err::class)
        ->and($result->error())->toBe(RegistrationError::EmailTaken);
});
```

Let the application's existing Pest or PHPUnit conventions determine test placement, database setup, and assertion style.

## Avoid Common Mistakes

- Do not return `null`, `false`, or an undocumented union for a failure already represented by `Err`.
- Do not call `unwrap()` merely to avoid handling an `Err`.
- Do not write helpers, traits, or macros that add callback methods such as `match()`, `map()`, or `andThen()` to results; use plain control flow.
- Do not catch `Panic` as part of normal application behavior.
- Do not turn unexpected infrastructure exceptions into generic `Err` values by default.
- Do not invent Laravel-specific package APIs; use `Result` directly in ordinary PHP classes.
- Code that still calls `match()`, `map()`, `mapError()`, or `andThen()` on a `Result` was written for 0.1; use the `prvious-result-upgrade-0-2` skill to rewrite it.
