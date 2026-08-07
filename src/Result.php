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

    /**
     * @template TOkResult
     * @template TErrResult
     *
     * @param callable(TValue): TOkResult $ok
     * @param callable(TError): TErrResult $err
     *
     * @return TOkResult|TErrResult
     */
    final public function match(callable $ok, callable $err): mixed
    {
        if ($this->isOk()) {
            return $ok($this->unwrap());
        }

        return $err($this->error());
    }

    /**
     * @template TMapped
     *
     * @param callable(TValue): TMapped $callback
     *
     * @return Result<TMapped, TError>
     */
    final public function map(callable $callback): Result
    {
        if ($this->isErr()) {
            return $this;
        }

        return self::ok($callback($this->unwrap()));
    }

    /**
     * @template TMappedError
     *
     * @param callable(TError): TMappedError $callback
     *
     * @return Result<TValue, TMappedError>
     */
    final public function mapError(callable $callback): Result
    {
        if ($this->isOk()) {
            return $this;
        }

        return self::err($callback($this->error()));
    }

    /**
     * @template TNextValue
     * @template TNextError
     *
     * @param callable(TValue): Result<TNextValue, TNextError> $callback
     *
     * @return Result<TNextValue, TError|TNextError>
     */
    final public function andThen(callable $callback): Result
    {
        if ($this->isErr()) {
            return $this;
        }

        return $callback($this->unwrap());
    }
}
