<?php

declare(strict_types=1);

use Prvious\Result\InvalidResultAccess;
use Prvious\Result\Result;
use Prvious\Result\Tests\Fixtures\SampleFailure;

function resultTestNullPayload(): mixed
{
    return null;
}

it('contains a successful value', function (): void {
    $value = new stdClass();
    $result = Result::success($value);

    expect($result->isSuccess())
        ->toBeTrue()
        ->and($result->isFailure())
        ->toBeFalse()
        ->and($result->value())
        ->toBe($value);
});

it('contains a failure value', function (): void {
    $result = Result::failure(SampleFailure::Rejected);

    expect($result->isSuccess())
        ->toBeFalse()
        ->and($result->isFailure())
        ->toBeTrue()
        ->and($result->error())
        ->toBe(SampleFailure::Rejected);
});

it('uses the branch tag rather than nullability', function (): void {
    $success = Result::success(resultTestNullPayload());
    $failure = Result::failure(resultTestNullPayload());

    expect($success->isSuccess())
        ->toBeTrue()
        ->and($success->value())
        ->toBeNull()
        ->and($failure->isFailure())
        ->toBeTrue()
        ->and($failure->error())
        ->toBeNull();
});

it('rejects value access on a failed result', function (): void {
    Result::failure(SampleFailure::Missing)->value();
})->throws(InvalidResultAccess::class, 'Cannot retrieve the success value from a failed result.');

it('rejects error access on a successful result', function (): void {
    Result::success('created')->error();
})->throws(InvalidResultAccess::class, 'Cannot retrieve the failure value from a successful result.');
