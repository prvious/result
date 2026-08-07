# Result

## What the package does

`prvious/result` is a tiny, framework-agnostic value object for returning an explicit success or an expected failure from PHP application code. A `Result<TSuccess, TFailure>` contains exactly one tagged payload, so either branch may hold any PHP value, including `null`.

The package uses PHPDoc generics because PHP does not have native generic classes. PHPStan or another compatible static analyzer provides generic type inference for the success and failure payloads.

## Requirements

- PHP 8.4 or newer

The package has no runtime dependency other than PHP. In particular, it has no Laravel runtime dependency and does not depend on Spatie Laravel Data.

## Installation

Install the package with Composer:

```shell
composer require prvious/result
```

## Basic usage

```php
<?php

declare(strict_types=1);

use Prvious\Result\Result;

/**
 * @return Result<string, RegistrationFailure>
 */
function register(bool $emailAvailable): Result
{
    if (! $emailAvailable) {
        return Result::failure(RegistrationFailure::EmailTaken);
    }

    return Result::success('user-123');
}

enum RegistrationFailure
{
    case EmailTaken;
}
```

`Result::success()` infers the type of its success value, and `Result::failure()` infers the type of its failure value. Application-specific failure enums and data objects remain in your application.

Spatie Laravel Data objects can be used as success payloads like any other value, but this package does not depend on Spatie Laravel Data.

## Laravel action example

An action-specific enum keeps the expected rejection paths explicit:

```php
enum MergeEntriesFailure
{
    case DifferentAccount;
    case SameEntry;
}
```

The action can then return either its successful data object or that enum:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Entries;

use App\Data\Entries\MergedEntryData;
use App\Data\Entries\MergeEntriesData;
use App\Models\Entry;
use Illuminate\Support\Facades\DB;
use Prvious\Result\Result;

final readonly class MergeEntries
{
    /**
     * @return Result<MergedEntryData, MergeEntriesFailure>
     */
    public function handle(MergeEntriesData $data): Result
    {
        $source = Entry::query()->findOrFail($data->sourceEntryId);
        $target = Entry::query()->findOrFail($data->targetEntryId);

        if ($source->is($target)) {
            return Result::failure(MergeEntriesFailure::SameEntry);
        }

        if ($source->account_id !== $target->account_id) {
            return Result::failure(MergeEntriesFailure::DifferentAccount);
        }

        return DB::transaction(function () use ($source, $target): Result {
            $target->increment('amount', $source->amount);

            $source->update([
                'merged_into_id' => $target->id,
            ]);

            return Result::success(
                new MergedEntryData(
                    entryId: $target->id,
                    amount: $target->fresh()->amount,
                ),
            );
        });
    }
}
```

This package does not automatically roll back database transactions. Check expected rejection paths before mutation, as above, or let unexpected exceptions escape the transaction so the framework can roll it back.

PHP 8.5 applications may place `#[\NoDiscard]` on action methods that return a `Result`, causing PHP to warn when a result is accidentally ignored:

```php
#[\NoDiscard]
public function handle(MergeEntriesData $data): Result
{
    // ...
}
```

The attribute belongs on application code when its minimum PHP version permits it; the package itself supports PHP 8.4.

## Handling a result at an application boundary

Interpret the result in a controller, command, job, or another application boundary:

```php
$result = $mergeEntries->handle(
    MergeEntriesData::from($request),
);

if ($result->isFailure()) {
    return match ($result->error()) {
        MergeEntriesFailure::SameEntry => back()->withErrors([
            'targetEntryId' => 'An entry cannot be merged into itself.',
        ]),

        MergeEntriesFailure::DifferentAccount => back()->withErrors([
            'targetEntryId' => 'Both entries must belong to the same account.',
        ]),
    };
}

$entry = $result->value();

return redirect()->route('entries.show', $entry->entryId);
```

Calling `value()` on a failure or `error()` on a success throws `InvalidResultAccess`. That exception represents programmer misuse of the result branch, not a business failure.

## Failure values with additional data

When a failure needs context, use a typed object, optionally behind an application-owned interface, rather than an untyped associative array:

```php
interface TransferFailure
{
}
```

```php
enum BasicTransferFailure implements TransferFailure
{
    case AccountClosed;
}
```

```php
final readonly class InsufficientBalance implements TransferFailure
{
    public function __construct(
        public int $requiredCents,
        public int $availableCents,
    ) {}
}
```

An action may then declare `Result<TransferReceipt, TransferFailure>` while returning either `BasicTransferFailure` or `InsufficientBalance` from its expected failure paths.

## Exceptions versus failures

Result failures are intended for expected branches that callers should handle, such as a rejected transfer or an unavailable username. These failures are values and do not throw.

Unexpected database, network, filesystem, and other infrastructure errors should continue to throw exceptions. `InvalidResultAccess` is also an exception because accessing the wrong branch is programmer misuse rather than an expected application outcome.

## Development

Install development dependencies with `composer install`, then use:

```shell
composer test
composer test:coverage
composer analyse
composer format
composer format:check
composer lint
composer mago:analyse
composer rector
composer rector:check
composer check
composer fix
```

The coverage command enforces 100 percent source coverage. `composer check` runs the complete validation, formatting, linting, static-analysis, Rector, and test suite.

## License

This package is open-source software licensed under the [MIT License](LICENSE.md).
