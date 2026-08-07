<?php

declare(strict_types=1);

namespace Prvious\Result;

/**
 * @template-covariant TError
 *
 * @extends Result<never, TError>
 */
final readonly class Err extends Result
{
    /**
     * @param TError $error
     */
    protected function __construct(
        private mixed $error,
    ) {}

    public function isOk(): false
    {
        return false;
    }

    public function isErr(): true
    {
        return true;
    }

    /**
     * @throws Panic
     */
    public function unwrap(): never
    {
        throw Panic::unwrapOnErr($this->error);
    }

    /**
     * @return TError
     */
    public function error(): mixed
    {
        return $this->error;
    }
}
