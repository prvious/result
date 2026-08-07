---
name: prvious-result-development
description: Use when writing, reviewing, or refactoring PHP code with the prvious/result package. Covers creating typed Ok and Err outcomes, declaring Result generics for PHPStan, matching and narrowing results, transforming values and errors, chaining operations, handling Panic correctly, testing result-based code, and using Result in Laravel actions, controllers, jobs, and services. Trigger for Prvious\Result, Result::ok(), Result::err(), match(), map(), mapError(), and andThen().
---

# Prvious Result Development

Use `prvious/result` for expected, caller-actionable failures while keeping successful and failed return types explicit. The package requires PHP 8.4+, has no runtime dependencies, and does not provide Laravel configuration, a facade, a service provider, or container bindings.

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

Allow unexpected database, filesystem, network, and provider failures to remain exceptions unless the application has an explicit recovery policy. Do not catch every `Throwable` and convert it to `Err`; exceptions thrown inside `match()`, `map()`, `mapError()`, or `andThen()` intentionally bubble unchanged.

## Consume Results Safely

Use `match()` at boundaries that must handle both variants, such as controllers, console commands, listeners, or jobs. Name the callbacks `ok` and `err` for clarity. `match()` returns the selected callback's raw value, not another `Result`.

```php
return $registerUser($attributes)->match(
    ok: fn (User $user): RedirectResponse => redirect()->route('users.show', $user),
    err: fn (RegistrationError $error): RedirectResponse => back()->withErrors([
        'email' => match ($error) {
            RegistrationError::EmailTaken => 'That email is already registered.',
        },
    ]),
);
```

Use `isOk()` or `isErr()` when control flow is clearer with an early return. PHPStan narrows the variant after either check.

```php
$result = $findUser($id);

if ($result->isErr()) {
    return null;
}

$user = $result->unwrap();
```

Call `unwrap()` only on a known or narrowed `Ok`. Call `error()` only on a known or narrowed `Err`.

## Transform and Compose

Select the operation by what the callback returns:

| Intent | Method | Callback returns | Behavior on the other variant |
| --- | --- | --- | --- |
| Transform a success | `map()` | Plain value | Preserve the existing `Err` |
| Transform an error | `mapError()` | Plain error | Preserve the existing `Ok` |
| Continue with a fallible operation | `andThen()` | Another `Result` | Preserve the existing `Err` |
| Finish by handling both variants | `match()` | Any terminal value | Run exactly one branch |

```php
$summary = $loadOrder($id)
    ->map(fn (Order $order): OrderData => OrderData::from($order))
    ->mapError(fn (OrderLookupError $error): ApiError => ApiError::from($error));
```

Use `andThen()` when the next operation already returns a `Result`:

```php
/**
 * @return Result<Receipt, CheckoutError|PaymentError>
 */
function checkout(Cart $cart): Result
{
    return validateCart($cart)->andThen(
        fn (ValidatedCart $validated): Result => chargeCart($validated),
    );
}
```

`andThen()` returns the callback's result directly and unions the previous and next error types. Use `mapError()` before or after chaining when the public method should expose one domain-level error type.

Never use `map()` for a callback that returns `Result`; that creates a nested `Result<Result<...>, ...>`. Use `andThen()` instead.

## Treat Panic as Misuse

`Prvious\Result\Panic` is a `LogicException` raised by `unwrap()` on `Err` or `error()` on `Ok`. It is not a third outcome and must not be used for business flow.

Do not catch `Panic` to recover from expected failures. Branch with `match()`, `isOk()`, or `isErr()` instead. When an incorrectly unwrapped `Err` contains a `Throwable`, `Panic` exposes it through both `$panic->payload` and `$panic->getPrevious()` for diagnosis.

## Test Both Variants

Cover the successful and expected-error paths. Assert the variant before accessing its payload, and verify short-circuiting when callback execution matters.

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
- Do not wrap an existing result with `Result::ok()`; return it directly or compose with `andThen()`.
- Do not catch `Panic` as part of normal application behavior.
- Do not turn unexpected infrastructure exceptions into generic `Err` values by default.
- Do not invent Laravel-specific package APIs; use `Result` directly in ordinary PHP classes.
