<?php

declare(strict_types=1);

use ShipFastLabs\PestEval\Scorers\ContainsAll;
use ShipFastLabs\PestEval\Scorers\ContainsAny;
use ShipFastLabs\PestEval\Scorers\ExactMatch;
use ShipFastLabs\PestEval\Scorers\JsonMatch;
use ShipFastLabs\PestEval\Scorers\JsonSchemaMatch;
use ShipFastLabs\PestEval\Scorers\RegexMatch;
use ShipFastLabs\PestEval\Tests\Fixtures\Agents\CapitalCityAgent;
use ShipFastLabs\PestEval\Tests\Fixtures\Agents\RefundPolicyAgent;

use function ShipFastLabs\PestEval\evaluate;

describe('fake mode', function (): void {
    it('bypasses the real agent and uses the faked response', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital of France?')
            ->fake(['Paris'])
            ->expect('Paris')
            ->score(ExactMatch::class)
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->runs[0]->output)->toBe('Paris');
    });

    it('cycles through multiple faked responses across runs', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital?')
            ->fake(['Paris', 'London', 'Berlin'])
            ->score(ContainsAny::class, terms: ['Paris', 'London', 'Berlin'])
            ->runs(3)
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->runs[0]->output)->toBe('Paris');
        expect($result->runs[1]->output)->toBe('London');
        expect($result->runs[2]->output)->toBe('Berlin');
    });

    it('reuses the last response when runs exceed faked responses', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital?')
            ->fake(['Tokyo'])
            ->score(ExactMatch::class)
            ->expect('Tokyo')
            ->runs(3)
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->runs[0]->output)->toBe('Tokyo');
        expect($result->runs[1]->output)->toBe('Tokyo');
        expect($result->runs[2]->output)->toBe('Tokyo');
    });

    it('works with deterministic scorers against faked output', function (): void {
        $result = evaluate(RefundPolicyAgent::class)
            ->withPrompt('What is your refund policy?')
            ->fake(['We offer full refunds within 30 days of purchase. Items must be in original condition with tags attached.'])
            ->score(ContainsAll::class, terms: ['30 days', 'original condition', 'tags'])
            ->score(RegexMatch::class, pattern: '/\d+ days/')
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->runs[0]->scorerResults)->toHaveCount(2);
        expect($result->runs[0]->scorerResults[0]->score)->toBe(1.0);
        expect($result->runs[0]->scorerResults[1]->score)->toBe(1.0);
    });

    it('fails when faked response does not meet threshold', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital of France?')
            ->fake(['I do not know'])
            ->expect('Paris')
            ->score(ExactMatch::class)
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeFalse();
        expect($result->avgScore())->toBe(0.0);
    });

    it('works with json match scorer', function (): void {
        $result = evaluate(RefundPolicyAgent::class)
            ->withPrompt('Return the policy as JSON')
            ->fake(['{"refund_window": 30, "currency": "USD"}'])
            ->expect('{"refund_window": 30, "currency": "USD"}')
            ->score(JsonMatch::class)
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->avgScore())->toBe(1.0);
    });

    it('works with json schema match scorer', function (): void {
        $result = evaluate(RefundPolicyAgent::class)
            ->withPrompt('Return the policy as JSON')
            ->fake(['{"refund_window": 30, "currency": "USD"}'])
            ->score(JsonSchemaMatch::class, schema: [
                'type' => 'object',
                'required' => ['refund_window', 'currency'],
                'properties' => [
                    'refund_window' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                ],
            ])
            ->threshold(0.7)
            ->run();

        expect($result->passed)->toBeTrue();
        expect($result->avgScore())->toBe(1.0);
    });

    it('assert throws on failed faked eval', function (): void {
        expect(
            fn (): \ShipFastLabs\PestEval\Eval\EvalResult => evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital of France?')
            ->fake(['wrong answer'])
            ->expect('Paris')
            ->score(ExactMatch::class)
            ->threshold(0.7)
            ->assert()
        )->toThrow(PHPUnit\Framework\AssertionFailedError::class);
    });

    it('tracks latency even with faked responses', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('test')
            ->fake(['response'])
            ->score(ExactMatch::class)
            ->expect('response')
            ->run();

        expect($result->avgLatencyMs)->toBeGreaterThanOrEqual(0.0);
        expect($result->runs[0]->latencyMs)->toBeGreaterThanOrEqual(0.0);
    });

    it('computes pass rate correctly with mixed faked results', function (): void {
        $result = evaluate(CapitalCityAgent::class)
            ->withPrompt('What is the capital?')
            ->fake(['Paris', 'wrong', 'Paris'])
            ->expect('Paris')
            ->score(ExactMatch::class)
            ->runs(3)
            ->threshold(0.7)
            ->run();

        expect($result->passRate)->toBe(2 / 3);
        expect($result->passed)->toBeFalse();
        expect($result->runs[0]->passed(0.7))->toBeTrue();
        expect($result->runs[1]->passed(0.7))->toBeFalse();
        expect($result->runs[2]->passed(0.7))->toBeTrue();
    });
});
