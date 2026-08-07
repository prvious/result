<?php

declare(strict_types=1);

namespace Prvious\Result;

/**
 * @template-covariant TSuccess
 * @template-covariant TFailure
 */
final readonly class Result
{
    private function __construct(
        private bool $successful,
        private mixed $payload,
    ) {}

    /**
     * @template TValue
     *
     * @param TValue $value
     *
     * @return self<TValue, never>
     */
    public static function success(mixed $value): self
    {
        /** @var self<TValue, never> $result */
        $result = new self(successful: true, payload: $value);

        return $result;
    }

    /**
     * @template TError
     *
     * @param TError $error
     *
     * @return self<never, TError>
     */
    public static function failure(mixed $error): self
    {
        /** @var self<never, TError> $result */
        $result = new self(successful: false, payload: $error);

        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->successful;
    }

    public function isFailure(): bool
    {
        return !$this->successful;
    }

    /**
     * @return TSuccess
     *
     * @throws InvalidResultAccess
     */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw InvalidResultAccess::valueFromFailure();
        }

        /** @var TSuccess $value */
        $value = $this->payload;

        return $value;
    }

    /**
     * @return TFailure
     *
     * @throws InvalidResultAccess
     */
    public function error(): mixed
    {
        if ($this->isSuccess()) {
            throw InvalidResultAccess::errorFromSuccess();
        }

        /** @var TFailure $error */
        $error = $this->payload;

        return $error;
    }
}
