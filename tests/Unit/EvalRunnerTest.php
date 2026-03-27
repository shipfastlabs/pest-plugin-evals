<?php

use ShipFastLabs\PestEval\Eval\EvalRunner;
use ShipFastLabs\PestEval\Scorers\ContainsAll;
use ShipFastLabs\PestEval\Scorers\ExactMatch;

describe('EvalRunner', function (): void {
    it('runs a single evaluation', function (): void {
        $runner = new EvalRunner();

        $result = $runner->run(
            task: fn (string $input): string => 'hello world',
            input: 'test',
            scorers: [new ExactMatch()],
            expected: 'hello world',
        );

        expect($result->passed)->toBeTrue();
        expect($result->runs)->toHaveCount(1);
        expect($result->avgScore())->toBe(1.0);
    });

    it('runs multiple times', function (): void {
        $runner = new EvalRunner();

        $result = $runner->run(
            task: fn (string $input): string => 'hello',
            input: 'test',
            scorers: [new ExactMatch()],
            runs: 5,
            expected: 'hello',
        );

        expect($result->runs)->toHaveCount(5);
        expect($result->passRate)->toBe(1.0);
    });

    it('computes pass rate correctly', function (): void {
        $runner = new EvalRunner();
        $callCount = 0;

        $result = $runner->run(
            task: function (string $input) use (&$callCount): string {
                $callCount++;

                return $callCount <= 2 ? 'expected' : 'different';
            },
            input: 'test',
            scorers: [new ExactMatch()],
            runs: 4,
            threshold: 0.7,
            expected: 'expected',
        );

        expect($result->passRate)->toBe(0.5);
        expect($result->passed)->toBeFalse();
    });

    it('groups scorer results by scorer class', function (): void {
        $runner = new EvalRunner();

        $result = $runner->run(
            task: fn (string $input): string => 'Our refund policy covers 30 days.',
            input: 'refund question',
            scorers: [
                new ContainsAll(terms: ['refund', '30 days']),
                new ExactMatch(),
            ],
            expected: 'exact output',
        );

        expect($result->scorerResults)->toHaveCount(2);
        expect($result->scoresByScorer())->toHaveCount(2);
    });

    it('tracks latency for each run', function (): void {
        $runner = new EvalRunner();

        $result = $runner->run(
            task: fn (string $input): string => 'response',
            input: 'test',
            scorers: [new ExactMatch()],
            expected: 'response',
        );

        expect($result->avgLatencyMs)->toBeGreaterThanOrEqual(0.0);
        expect($result->runs[0]->latencyMs)->toBeGreaterThanOrEqual(0.0);
    });
});
