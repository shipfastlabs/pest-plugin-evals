<?php

declare(strict_types=1);

use ShipFastLabs\PestEval\Eval\EvalBuilder;
use ShipFastLabs\PestEval\Scorers\ExactMatch;

it('uses the provided threshold in toPassEval', function (): void {
    $attempts = 0;

    $result = (new EvalBuilder())
        ->task(function (string $input) use (&$attempts): string {
            $attempts++;

            return $attempts <= 2 ? 'ok' : 'not ok';
        })
        ->withPrompt('test')
        ->expect('ok')
        ->score(ExactMatch::class)
        ->runs(3)
        ->threshold(0.6)
        ->run();

    expect($result->passed)->toBeTrue();
    expect($result->passRate)->toBe(2 / 3);

    $thrown = null;

    try {
        expect($result)->toPassEval(threshold: 0.8);
    } catch (\Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown?->getMessage())->toContain('Eval failed: pass rate 66.7% below threshold 80.0%');
});
