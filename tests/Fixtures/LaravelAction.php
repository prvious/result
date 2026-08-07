<?php

declare(strict_types=1);

namespace Prvious\Result\Tests\Fixtures;

use Prvious\Result\Result;

final class LaravelAction
{
    /**
     * @return Result<string, SampleFailure>
     */
    public function handle(bool $allowed): Result
    {
        if (!$allowed) {
            return Result::failure(SampleFailure::Rejected);
        }

        return Result::success('created');
    }
}
