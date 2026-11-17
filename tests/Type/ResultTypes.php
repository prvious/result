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
 *
 * @return Result<int, SampleError>
 */
function assertGuardClausePropagatesErr(Result $result): Result
{
    if ($result->isErr()) {
        return $result;
    }

    assertType('Prvious\Result\Ok<string>', $result);

    return Result::ok(resultTypeLength($result->unwrap()));
}

/**
 * @param Result<string, SampleError> $result
 *
 * @return Result<string, OtherError>
 */
function assertGuardClauseTranslatesErr(Result $result): Result
{
    if ($result->isErr()) {
        return Result::err(translateError($result->error()));
    }

    return $result;
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
