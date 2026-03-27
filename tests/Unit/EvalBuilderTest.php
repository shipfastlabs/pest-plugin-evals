<?php

use ShipFastLabs\PestEval\Eval\EvalBuilder;
use ShipFastLabs\PestEval\Eval\EvalResult;
use ShipFastLabs\PestEval\Scorers\ContainsAll;
use ShipFastLabs\PestEval\Scorers\ExactMatch;

describe('EvalBuilder', function (): void {
    it('runs a basic eval with a task', function (): void {
        $result = (new EvalBuilder())
            ->task(fn (string $input): string => "The answer to '{$input}' is 42.")
            ->withPrompt('What is the meaning of life?')
            ->score(ContainsAll::class, terms: ['42'])
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->avgScore())->toBe(1.0);
    });

    it('supports faked responses', function (): void {
        $result = (new EvalBuilder())
            ->agent('FakepAgent')
            ->withPrompt('test')
            ->fake(['Our refund policy allows returns within 30 days.'])
            ->score(ContainsAll::class, terms: ['refund', '30 days'])
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
    });

    it('supports multiple runs', function (): void {
        $callCount = 0;

        $result = (new EvalBuilder())
            ->task(function (string $input) use (&$callCount): string {
                $callCount++;

                return 'response';
            })
            ->withPrompt('test')
            ->score(ExactMatch::class)
            ->expect('response')
            ->runs(3)
            ->threshold(0.7)
            ->run();

        expect($callCount)->toBe(3);
        expect($result->runs)->toHaveCount(3);
    });

    it('supports expected output', function (): void {
        $result = (new EvalBuilder())
            ->task(fn (string $input): string => 'Paris')
            ->withPrompt('Capital of France?')
            ->expect('Paris')
            ->score(ExactMatch::class)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->avgScore())->toBe(1.0);
    });

    it('fails when threshold is not met', function (): void {
        $result = (new EvalBuilder())
            ->task(fn (string $input): string => 'wrong answer')
            ->withPrompt('test')
            ->expect('right answer')
            ->score(ExactMatch::class)
            ->threshold(0.5)
            ->run();

        expect($result->passed)->toBeFalse();
        expect($result->avgScore())->toBe(0.0);
    });

    it('supports multiple scorers', function (): void {
        $result = (new EvalBuilder())
            ->task(fn (string $input): string => 'Our refund policy allows returns within 30 days.')
            ->withPrompt('What is your return policy?')
            ->score(ContainsAll::class, terms: ['refund', '30 days'])
            ->score(ContainsAll::class, terms: ['returns', 'policy'])
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->runs[0]->scorerResults)->toHaveCount(2);
    });

    it('throws when no agent or task configured', function (): void {
        expect(fn (): EvalResult => (new EvalBuilder())->withPrompt('test')->run())
            ->toThrow(RuntimeException::class);
    });

    it('tracks latency', function (): void {
        $result = (new EvalBuilder())
            ->task(function (string $input): string {
                usleep(10_000);
                return 'response';
            })
            ->withPrompt('test')
            ->score(ExactMatch::class)
            ->expect('response')
            ->run();

        expect($result->avgLatencyMs)->toBeGreaterThan(5.0);
    });
});
