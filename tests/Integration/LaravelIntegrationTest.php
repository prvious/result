<?php

declare(strict_types=1);

use Prvious\Result\Tests\Fixtures\LaravelAction;
use Prvious\Result\Tests\Fixtures\SampleFailure;

it('works inside a Laravel application without a service provider', function (): void {
    $app = $this->app ?? throw new LogicException('Testbench application was not initialized.');

    $app->singleton(LaravelAction::class, static fn(): LaravelAction => new LaravelAction());

    $action = $app->make(LaravelAction::class);
    $result = $action->handle(allowed: false);

    expect($result->isFailure())->toBeTrue()->and($result->error())->toBe(SampleFailure::Rejected);
});
