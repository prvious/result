<?php

declare(strict_types=1);

namespace Prvious\Result;

use LogicException;
use Throwable;

final class Panic extends LogicException
{
    private function __construct(
        string $message,
        public readonly mixed $payload,
        ?Throwable $previous = null,
    ) {
        parent::__construct(message: $message, code: 0, previous: $previous);
    }

    public static function unwrapOnErr(mixed $error): self
    {
        return new self(
            message: 'Called unwrap() on an Err result.',
            payload: $error,
            previous: $error instanceof Throwable ? $error : null,
        );
    }

    public static function errorOnOk(mixed $value): self
    {
        return new self(message: 'Called error() on an Ok result.', payload: $value);
    }
}
