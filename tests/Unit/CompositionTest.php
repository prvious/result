<?php

declare(strict_types=1);

use Prvious\Result\Err;
use Prvious\Result\Ok;
use Prvious\Result\Result;
use Prvious\Result\Tests\Fixtures\OtherError;
use Prvious\Result\Tests\Fixtures\SampleError;

/**
 * @param Closure(): mixed $callback
 */
function captureCompositionThrowable(Closure $callback): Throwable
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        return $throwable;
    }

    throw new RuntimeException('Expected the callback to throw.');
}

it('matches only the Ok branch and returns its raw value', function (): void {
    $okCalled = false;
    $errCalled = false;
    $raw = new stdClass();

    $matched = Result::ok('created')->match(ok: function (string $_value) use (&$okCalled, $raw): stdClass {
        $okCalled = true;

        return $raw;
    }, err: function (mixed $_error) use (&$errCalled): stdClass {
        $errCalled = true;

        return new stdClass();
    });

    expect($okCalled)->toBeTrue()->and($errCalled)->toBeFalse()->and($matched)->toBe($raw);
});

it('matches only the Err branch and returns its raw value', function (): void {
    $okCalled = false;
    $errCalled = false;
    $raw = new stdClass();

    $matched = Result::err(SampleError::Rejected)->match(ok: function (mixed $_value) use (&$okCalled): stdClass {
        $okCalled = true;

        return new stdClass();
    }, err: function (SampleError $_error) use (&$errCalled, $raw): stdClass {
        $errCalled = true;

        return $raw;
    });

    expect($okCalled)->toBeFalse()->and($errCalled)->toBeTrue()->and($matched)->toBe($raw);
});

it('lets exceptions from either selected match callback bubble unchanged', function (): void {
    $okException = new RuntimeException('Ok callback failed.');
    $errException = new RuntimeException('Err callback failed.');

    $fromOk = captureCompositionThrowable(static fn(): mixed => Result::ok('created')->match(
        ok: static fn(string $_value): never => throw $okException,
        err: static fn(mixed $_error): null => null,
    ));
    $fromErr = captureCompositionThrowable(static fn(): mixed => Result::err(SampleError::Rejected)->match(
        ok: static fn(mixed $_value): null => null,
        err: static fn(SampleError $_error): never => throw $errException,
    ));

    expect($fromOk)->toBe($okException)->and($fromErr)->toBe($errException);
});

it('maps an Ok value into a new Ok', function (): void {
    $mapped = Result::ok('four')->map(static fn(string $value): int => strlen($value));

    expect($mapped)->toBeInstanceOf(Ok::class)->and($mapped->unwrap())->toBe(4);
});

it('short-circuits map for Err without invoking the callback', function (): void {
    $called = false;
    $result = Result::err(SampleError::Rejected);

    $mapped = $result->map(function (mixed $_value) use (&$called): string {
        $called = true;

        return 'should not happen';
    });

    expect($called)->toBeFalse()->and($mapped)->toBe($result)->and($mapped->error())->toBe(SampleError::Rejected);
});

it('lets exceptions from map bubble unchanged', function (): void {
    $exception = new RuntimeException('Map failed.');

    $thrown = captureCompositionThrowable(static fn(): Result => Result::ok('created')->map(
        static fn(string $_value): never => throw $exception,
    ));

    expect($thrown)->toBe($exception);
});

it('maps an Err value into a new Err', function (): void {
    $mapped = Result::err(SampleError::Rejected)->mapError(
        static fn(SampleError $_error): OtherError => OtherError::Translated,
    );

    expect($mapped)->toBeInstanceOf(Err::class)->and($mapped->error())->toBe(OtherError::Translated);
});

it('short-circuits mapError for Ok without invoking the callback', function (): void {
    $called = false;
    $value = new stdClass();
    $result = Result::ok($value);

    $mapped = $result->mapError(function (mixed $_error) use (&$called): OtherError {
        $called = true;

        return OtherError::Translated;
    });

    expect($called)->toBeFalse()->and($mapped)->toBe($result)->and($mapped->unwrap())->toBe($value);
});

it('lets exceptions from mapError bubble unchanged', function (): void {
    $exception = new RuntimeException('Error mapping failed.');

    $thrown = captureCompositionThrowable(static fn(): Result => Result::err(SampleError::Rejected)->mapError(
        static fn(SampleError $_error): never => throw $exception,
    ));

    expect($thrown)->toBe($exception);
});

it('returns an Ok from andThen directly', function (): void {
    $next = Result::ok(7);

    $chained = Result::ok('created')->andThen(static fn(string $_value): Ok => $next);

    expect($chained)->toBe($next)->and($chained->unwrap())->toBe(7);
});

it('returns an Err from andThen directly without nesting it', function (): void {
    $next = Result::err(OtherError::Empty);

    $chained = Result::ok('created')->andThen(static fn(string $_value): Err => $next);

    expect($chained)->toBe($next)->and($chained->error())->toBe(OtherError::Empty);
});

it('short-circuits andThen for Err without invoking the callback', function (): void {
    $called = false;
    $result = Result::err(SampleError::Rejected);

    $chained = $result->andThen(function (mixed $_value) use (&$called): Ok {
        $called = true;

        return Result::ok('should not happen');
    });

    expect($called)->toBeFalse()->and($chained)->toBe($result)->and($chained->error())->toBe(SampleError::Rejected);
});

it('lets exceptions from andThen bubble unchanged', function (): void {
    $exception = new RuntimeException('Chaining failed.');

    $thrown = captureCompositionThrowable(static fn(): Result => Result::ok('created')->andThen(
        static fn(string $value): Result => throwDuringAndThen($value, $exception),
    ));

    expect($thrown)->toBe($exception);
});

/**
 * @return Result<string, SampleError>
 */
function throwDuringAndThen(string $_value, RuntimeException $exception): Result
{
    throw $exception;
}
