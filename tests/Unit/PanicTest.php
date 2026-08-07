<?php

declare(strict_types=1);

use Prvious\Result\Panic;
use Prvious\Result\Result;

/**
 * @param Closure(): mixed $callback
 */
function captureResultPanic(Closure $callback): Panic
{
    try {
        $callback();
    } catch (Panic $panic) {
        return $panic;
    }

    throw new RuntimeException('Expected the callback to throw Panic.');
}

it('panics when unwrapping an Err', function (): void {
    $payload = new stdClass();
    $panic = captureResultPanic(static fn(): mixed => Result::err($payload)->unwrap());

    expect($panic->getMessage())
        ->toBe('Called unwrap() on an Err result.')
        ->and($panic->payload)
        ->toBe($payload)
        ->and($panic->getPrevious())
        ->toBeNull();
});

it('extends LogicException', function (): void {
    $panic = new ReflectionClass(Panic::class);

    expect($panic->isSubclassOf(LogicException::class))->toBeTrue();
});

it('panics when reading the error from an Ok', function (): void {
    $payload = new stdClass();
    $panic = captureResultPanic(static fn(): mixed => Result::ok($payload)->error());

    expect($panic->getMessage())
        ->toBe('Called error() on an Ok result.')
        ->and($panic->payload)
        ->toBe($payload)
        ->and($panic->getPrevious())
        ->toBeNull();
});

it('preserves a Throwable error as the previous exception', function (): void {
    $error = new RuntimeException('Transport failed.');
    $panic = captureResultPanic(static fn(): mixed => Result::err($error)->unwrap());

    expect($panic->payload)->toBe($error)->and($panic->getPrevious())->toBe($error);
});

it('does not treat a Throwable Ok value as the previous exception', function (): void {
    $value = new RuntimeException('An ordinary successful value.');
    $panic = captureResultPanic(static fn(): mixed => Result::ok($value)->error());

    expect($panic->payload)->toBe($value)->and($panic->getPrevious())->toBeNull();
});
