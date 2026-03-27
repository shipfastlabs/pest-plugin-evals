<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval;

use Closure;
use Pest\Expectation;
use Pest\Plugin;
use ShipFastLabs\PestEval\Eval\EvalBuilder;
use ShipFastLabs\PestEval\Eval\EvalResult;
use ShipFastLabs\PestEval\Scorers\Scorer;
use ShipFastLabs\PestEval\Scorers\ScorerResult;

Plugin::uses(Concerns\InteractsWithEvals::class);

function evaluate(string $agentClass): EvalBuilder
{
    return (new EvalBuilder())->agent($agentClass);
}

function evaluateTask(Closure $task): EvalBuilder
{
    return (new EvalBuilder())->task($task);
}

expect()->extend('toPassScorer', function (string|Scorer $scorer, float $threshold = 0.7, mixed ...$args): Expectation {
    /** @var Expectation<mixed> $this */
    $value = $this->value;

    if (! is_string($value)) {
        throw new \RuntimeException('toPassScorer expects a string value (the AI response output).');
    }

    $scorerInstance = is_string($scorer) ? new $scorer(...$args) : $scorer;

    if (! $scorerInstance instanceof Scorer) {
        throw new \RuntimeException('The provided class does not implement the Scorer interface.');
    }

    $result = $scorerInstance->score('', $value);

    expect($result->score)->toBeGreaterThanOrEqual(
        $threshold,
        "Scorer {$result->scorer} scored {$result->score} (threshold: {$threshold}). Reasoning: {$result->reasoning}",
    );

    return $this;
});

expect()->extend('toPassEval', function (float $threshold = 0.7): Expectation {
    /** @var Expectation<mixed> $this */
    $value = $this->value;

    if (! $value instanceof EvalResult) {
        throw new \RuntimeException('toPassEval expects an EvalResult instance.');
    }

    expect($value->passRate)->toBeGreaterThanOrEqual(
        $threshold,
        'Eval failed: pass rate '.number_format($value->passRate * 100, 1).'% below threshold '.number_format($threshold * 100, 1).'%',
    );

    return $this;
});

expect()->extend('toHaveAvgScore', function (float $min): Expectation {
    /** @var Expectation<mixed> $this */
    $value = $this->value;

    if ($value instanceof EvalResult) {
        $avg = $value->avgScore();
    } elseif ($value instanceof ScorerResult) {
        $avg = $value->score;
    } else {
        throw new \RuntimeException('toHaveAvgScore expects an EvalResult or ScorerResult instance.');
    }

    expect($avg)->toBeGreaterThanOrEqual(
        $min,
        "Average score {$avg} is below minimum {$min}",
    );

    return $this;
});
