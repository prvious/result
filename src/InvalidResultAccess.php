<?php

declare(strict_types=1);

namespace Prvious\Result;

use LogicException;

final class InvalidResultAccess extends LogicException
{
    public static function valueFromFailure(): self
    {
        return new self('Cannot retrieve the success value from a failed result.');
    }

    public static function errorFromSuccess(): self
    {
        return new self('Cannot retrieve the failure value from a successful result.');
    }
}
