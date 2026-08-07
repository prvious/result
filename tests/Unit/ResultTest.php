<?php

declare(strict_types=1);

use Prvious\Result\Err;
use Prvious\Result\Ok;
use Prvious\Result\Result;
use Prvious\Result\Tests\Fixtures\SampleError;

/**
 * @return Result<mixed, never>
 */
function resultTestOk(mixed $value): Result
{
    return Result::ok($value);
}

/**
 * @return Result<never, mixed>
 */
function resultTestErr(mixed $error): Result
{
    return Result::err($error);
}

function resultTestNullPayload(): mixed
{
    return null;
}

it('constructs and inspects an Ok result', function (): void {
    $value = new stdClass();
    $result = resultTestOk($value);

    expect($result)
        ->toBeInstanceOf(Ok::class)
        ->and($result->isOk())
        ->toBeTrue()
        ->and($result->isErr())
        ->toBeFalse()
        ->and($result->unwrap())
        ->toBe($value);
});

it('constructs and inspects an Err result', function (): void {
    $result = resultTestErr(SampleError::Rejected);

    expect($result)
        ->toBeInstanceOf(Err::class)
        ->and($result->isOk())
        ->toBeFalse()
        ->and($result->isErr())
        ->toBeTrue()
        ->and($result->error())
        ->toBe(SampleError::Rejected);
});

it('accepts null in either variant', function (): void {
    $ok = resultTestOk(resultTestNullPayload());
    $err = resultTestErr(resultTestNullPayload());

    expect($ok->unwrap())->toBeNull()->and($err->error())->toBeNull();
});
