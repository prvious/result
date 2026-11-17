---
name: prvious-result-upgrade-0-2
description: Use when upgrading prvious/result from 0.1 to 0.2, or when PHP code calls match(), map(), mapError(), or andThen() on a Prvious\Result\Result. Rewrites those removed callback methods into isErr()/isOk() guard clauses that read values with unwrap() and error(), then bumps the Composer constraint.
---

# Upgrade prvious/result from 0.1 to 0.2

## What Changed

0.2 removes the four callback methods from `Result`: `match()`, `map()`, `mapError()`, and `andThen()`.

Everything else is unchanged: `Result::ok()`, `Result::err()`, `isOk()`, `isErr()`, `unwrap()`, `error()`, `Panic`, the `Ok` and `Err` classes, and the generic types.

Code that still calls a removed method fails at runtime with `Error: Call to undefined method Prvious\Result\Ok::match()` (or `Err::`, or the other method names). PHPStan reports it as an undefined method on `Prvious\Result\Result`.

## Upgrade Order

The rewritten code only uses methods that exist in both versions, so rewrite first and bump last:

1. Find every call site (see below).
2. Rewrite each one with the patterns in this skill without changing behavior.
3. Run the test suite, and PHPStan if the project has it, while still on 0.1.
4. Change the constraint in `composer.json` to `"prvious/result": "^0.2"` and run `composer update prvious/result`. A `^0.1` constraint never resolves to 0.2.
5. Run the test suite again.

## Find Call Sites

Callers often do not import `Result`, for example a controller calling `$action->handle($input)->match(...)`. Searching for imports is not enough.

Search all PHP files outside `vendor/` for these regular expressions:

- `->(match|mapError|andThen)\(`
- `->map\(`

Before rewriting a hit, confirm that the receiver is a `Prvious\Result\Result`: the method that produced it declares `@return Result<...>` or returns `Result::ok()` or `Result::err()`. Leave everything else alone, especially Laravel's `Collection::map()` and `Stringable::match()`, and PHP's native `match (...)` expression.

## Rewrite Rules

- Assign the result to a variable, check `isErr()` and return early, then continue on the success path with `unwrap()`.
- Every `unwrap()` must come after a check. Every `error()` must be inside an `isErr()` branch, or after `isOk()` returned `false`.
- Inline callback bodies. A callable passed by name, such as `OrderData::from(...)`, `$this->present(...)`, or `[$this, 'present']`, becomes a direct call with the unwrapped value or error: `OrderData::from($result->unwrap())`.
- A callback that ignored its argument (`fn () => ...`) is inlined without reading the value.
- Callbacks that captured variables with `use (&$x)` or changed outer state become ordinary statements in the enclosing scope; drop the `use` clause.
- If you assign the unwrapped value to a new variable, choose a name that does not overwrite a variable the surrounding code still uses.
- When several results are in scope, name each after its domain (`$registration`, `$charge`) instead of reusing `$result`.
- If the rewrite introduces `Result::ok()` or `Result::err()` in a file that does not import it, add `use Prvious\Result\Result;`.
- Keep `@return Result<...>` PHPDoc unchanged; the rewritten code returns the same types.
- Do not add `try`/`catch`. In 0.1, exceptions thrown inside callbacks bubbled unchanged, and inlined code behaves the same way.

## Rewrite match()

When the `match()` result is returned, which is the common case:

```php
// 0.1
return $registerUser($attributes)->match(
    ok: fn (User $user): RedirectResponse => redirect()->route('users.show', $user),
    err: fn (RegistrationError $error): RedirectResponse => back()->withErrors([
        'email' => match ($error) {
            RegistrationError::EmailTaken => 'That email is already registered.',
        },
    ]),
);

// 0.2
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

When the `match()` result is assigned and execution continues, use a ternary for single expressions or `if/else` that assigns the same variable in both branches:

```php
// 0.1
$label = $result->match(
    ok: fn (Order $order): string => $order->number,
    err: fn (OrderLookupError $error): string => 'Unavailable',
);

// 0.2
$label = $result->isOk() ? $result->unwrap()->number : 'Unavailable';
```

When the `match()` return value was ignored and the callbacks only had side effects, use `if ($result->isErr()) { ... } else { ... }`.

## Rewrite map()

```php
// 0.1
return $this->loadOrder($id)->map(fn (Order $order): OrderData => OrderData::from($order));

// 0.2
$result = $this->loadOrder($id);

if ($result->isErr()) {
    return $result;
}

return Result::ok(OrderData::from($result->unwrap()));
```

Returning the narrowed `Err` passes the same instance up, exactly as `map()` did.

## Rewrite mapError()

```php
// 0.1
return $this->loadOrder($id)->mapError(fn (OrderLookupError $error): ApiError => ApiError::from($error));

// 0.2
$result = $this->loadOrder($id);

if ($result->isErr()) {
    return Result::err(ApiError::from($result->error()));
}

return $result;
```

## Rewrite andThen()

```php
// 0.1
return validateCart($cart)->andThen(fn (ValidatedCart $validated): Result => chargeCart($validated));

// 0.2
$validated = validateCart($cart);

if ($validated->isErr()) {
    return $validated;
}

return chargeCart($validated->unwrap());
```

## Rewrite Chains

Unroll the chain from left to right, with one variable and one check per fallible step. Where each transformation applies matters:

- A `mapError()` at the end of a chain translated every error that reached it. Apply the translation at every early return before that point.
- A `mapError()` in the middle of a chain only translated errors from the steps before it.
- A `map()` only transformed the value at its position in the chain.

```php
// 0.1
return validateCart($cart)
    ->andThen(fn (ValidatedCart $validated): Result => chargeCart($validated))
    ->map(fn (Charge $charge): Receipt => Receipt::from($charge))
    ->mapError(fn (CheckoutError|PaymentError $error): ApiError => ApiError::from($error));

// 0.2
$validated = validateCart($cart);

if ($validated->isErr()) {
    return Result::err(ApiError::from($validated->error()));
}

$charge = chargeCart($validated->unwrap());

if ($charge->isErr()) {
    return Result::err(ApiError::from($charge->error()));
}

return Result::ok(Receipt::from($charge->unwrap()));
```

## Verify

- The searches above return no call sites whose receiver is a `Result`.
- Every `unwrap()` and `error()` added by the rewrite sits behind a check.
- Responses, error messages, and side effects are unchanged.
- Tests pass on 0.1 before the bump and on 0.2 after it. If the project runs PHPStan, it reports no undefined-method errors on `Prvious\Result` classes.

## Do Not

- Do not add helpers, traits, or macros that recreate `match()`, `map()`, `mapError()`, or `andThen()`.
- Do not replace a check with a `try`/`catch` around `Panic`.
- Do not drop an error branch that `match()` handled by calling `unwrap()` directly.
