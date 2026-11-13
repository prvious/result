<?php

declare(strict_types=1);

namespace Prvious\Result;

/**
 * @template-covariant TValue
 * @template-covariant TError
 *
 * @phpstan-sealed Ok|Err
 */
abstract readonly class Result
{
    /**
     * @template TOk
     *
     * @param TOk $value
     *
     * @return Ok<TOk>
     */
    final public static function ok(mixed $value): Ok
    {
        return new Ok($value);
    }

    /**
     * @template TErr
     *
     * @param TErr $error
     *
     * @return Err<TErr>
     */
    final public static function err(mixed $error): Err
    {
        return new Err($error);
    }

    /**
     * @phpstan-assert-if-true Ok<TValue> $this
     * @phpstan-assert-if-false Err<TError> $this
     */
    abstract public function isOk(): bool;

    /**
     * @phpstan-assert-if-true Err<TError> $this
     * @phpstan-assert-if-false Ok<TValue> $this
     */
    abstract public function isErr(): bool;

    /**
     * @return TValue
     *
     * @throws Panic
     */
    abstract public function unwrap(): mixed;

    /**
     * @return TError
     *
     * @throws Panic
     */
    abstract public function error(): mixed;
}
