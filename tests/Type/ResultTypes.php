<?php

declare(strict_types=1);

namespace Prvious\Result\Tests\Type;

use Prvious\Result\Result;
use Prvious\Result\Tests\Fixtures\OtherError;
use Prvious\Result\Tests\Fixtures\SampleError;

use function PHPStan\Testing\assertType;

class ParentPayload {}

final class ChildPayload extends ParentPayload {}

function resultTypeLength(string $value): int
{
    return strlen($value);
}

function translateError(SampleError $error): OtherError
{
    return match ($error) {
        SampleError::Missing => OtherError::Empty,
        SampleError::Rejected => OtherError::Translated,
    };
}

function sampleErrorName(SampleError $error): string
{
    return $error->name;
}

function assertFactoryInference(string $value, SampleError $error): void
{
    $ok = Result::ok($value);
    $err = Result::err($error);

    assertType('Prvious\Result\Ok<string>', $ok);
    assertType('Prvious\Result\Err<Prvious\Result\Tests\Fixtures\SampleError>', $err);
}

/**
 * @return Result<string, SampleError>
 */
function sampleAction(bool $allowed): Result
{
    if (!$allowed) {
        return Result::err(SampleError::Rejected);
    }

    return Result::ok('created');
}

/**
 * @param Result<string, SampleError> $result
 */
function assertAccessorInference(Result $result): void
{
    assertType('string', $result->unwrap());
    assertType('Prvious\Result\Tests\Fixtures\SampleError', $result->error());
}

/**
 * @param Result<string, SampleError> $result
 */
function assertIsOkNarrowing(Result $result): void
{
    if ($result->isOk()) {
        assertType('Prvious\Result\Ok<string>', $result);
    } else {
        assertType('Prvious\Result\Err<Prvious\Result\Tests\Fixtures\SampleError>', $result);
    }
}

/**
 * @param Result<string, SampleError> $result
 */
function assertIsErrNarrowing(Result $result): void
{
    if ($result->isErr()) {
        assertType('Prvious\Result\Err<Prvious\Result\Tests\Fixtures\SampleError>', $result);
    } else {
        assertType('Prvious\Result\Ok<string>', $result);
    }
}

/**
 * @param Result<string, SampleError> $result
 */
function assertMapInference(Result $result): void
{
    $mapped = $result->map(resultTypeLength(...));

    assertType('Prvious\Result\Result<int, Prvious\Result\Tests\Fixtures\SampleError>', $mapped);
}

/**
 * @param Result<string, SampleError> $result
 */
function assertMapErrorInference(Result $result): void
{
    $mapped = $result->mapError(translateError(...));

    assertType('Prvious\Result\Result<string, Prvious\Result\Tests\Fixtures\OtherError>', $mapped);
}

/**
 * @return Result<int, OtherError>
 */
function nextAction(string $value): Result
{
    if ($value === '') {
        return Result::err(OtherError::Empty);
    }

    return Result::ok(resultTypeLength($value));
}

/**
 * @param Result<string, SampleError> $result
 */
function assertAndThenInference(Result $result): void
{
    $chained = $result->andThen(nextAction(...));

    assertType(
        'Prvious\Result\Result<int, Prvious\Result\Tests\Fixtures\OtherError|Prvious\Result\Tests\Fixtures\SampleError>',
        $chained,
    );
}

/**
 * @param Result<string, SampleError> $result
 */
function assertMatchInference(Result $result): void
{
    $matched = $result->match(ok: resultTypeLength(...), err: sampleErrorName(...));

    assertType('int|string', $matched);
}

/**
 * @param Result<ParentPayload, SampleError> $_result
 */
function acceptsParentResult(Result $_result): void {}

function assertCovariance(ChildPayload $child, SampleError $error): void
{
    $ok = Result::ok($child);
    $err = Result::err($error);

    acceptsParentResult($ok);
    acceptsParentResult($err);
}
