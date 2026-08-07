<?php

declare(strict_types=1);

namespace Prvious\Result\Tests\Type;

use Prvious\Result\Result;
use Prvious\Result\Tests\Fixtures\SampleFailure;

use function PHPStan\Testing\assertType;

function assertSuccessInference(string $value): void
{
    $result = Result::success($value);

    assertType('Prvious\Result\Result<string, never>', $result);

    assertType('string', $result->value());
}

function assertFailureInference(SampleFailure $failure): void
{
    $result = Result::failure($failure);

    assertType('Prvious\Result\Result<never, Prvious\Result\Tests\Fixtures\SampleFailure>', $result);

    assertType('Prvious\Result\Tests\Fixtures\SampleFailure', $result->error());
}
