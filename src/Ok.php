<?php

declare(strict_types=1);

namespace Prvious\Result;

/**
 * @template-covariant TValue
 *
 * @extends Result<TValue, never>
 */
final readonly class Ok extends Result
{
    /**
     * @param TValue $value
     */
    protected function __construct(
        private mixed $value,
    ) {}

    public function isOk(): true
    {
        return true;
    }

    public function isErr(): false
    {
        return false;
    }

    /**
     * @return TValue
     */
    public function unwrap(): mixed
    {
        return $this->value;
    }

    /**
     * @throws Panic
     */
    public function error(): never
    {
        throw Panic::errorOnOk($this->value);
    }
}
