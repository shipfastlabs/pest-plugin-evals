<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval;

use Closure;
use Pest\Expectation;
use ShipFastLabs\PestEval\Eval\EvalExpectationContext;
use ShipFastLabs\PestEval\Eval\EvalReport;
use ShipFastLabs\PestEval\Scorers\AgentTrajectory;
use ShipFastLabs\PestEval\Scorers\Factuality;
use ShipFastLabs\PestEval\Scorers\LlmJudge;
use ShipFastLabs\PestEval\Scorers\Relevance;
use ShipFastLabs\PestEval\Scorers\Safety;
use ShipFastLabs\PestEval\Scorers\Scorer;
use ShipFastLabs\PestEval\Scorers\SemanticSimilarity;
use ShipFastLabs\PestEval\Scorers\ToolCallMatch;

/**
 * @param  list<string>  $fake
 */
function expectAgent(
    string|Closure $agent,
    string $prompt,
    int $runs = 1,
    array $fake = [],
): mixed {
    $ctx = new EvalExpectationContext(
        prompt: $prompt,
        agentName: is_string($agent) ? class_basename($agent) : 'Task',
        runs: $runs,
        fakedResponses: $fake,
    );

    EvalExpectationContext::$current = $ctx;

    $outputs = $ctx->resolveOutputs($agent);

    if (count($outputs) === 1) {
        return expect($outputs[0]);
    }

    return expect($outputs)->each;
}

/**
 * @internal
 */
function assertScorerResult(Scorer $scorer, string $output, float $threshold, ?string $expected = null): void
{
    $input = EvalExpectationContext::currentPrompt();
    $agent = EvalExpectationContext::currentAgentName();

    $result = $scorer->score($input, $output, $expected);

    EvalReport::instance()->addScorerResult($agent, $result->scorer, $result->score, $threshold);

    $scorerName = class_basename($result->scorer);

    expect($result->score)->toBeGreaterThanOrEqual(
        $threshold,
        "{$scorerName} scored {$result->score} (threshold: {$threshold}). {$result->reasoning}",
    );
}

expect()->extend('toBeRelevant', function (float $threshold = 0.7): Expectation {
    /** @var Expectation<string> $this */
    assertScorerResult(new Relevance(), $this->value, $threshold);

    return $this;
});

expect()->extend('toBeSafe', function (float $threshold = 0.7): Expectation {
    /** @var Expectation<string> $this */
    assertScorerResult(new Safety(), $this->value, $threshold);

    return $this;
});

expect()->extend('toBeFactual', function (float $threshold = 0.7, ?string $expected = null): Expectation {
    /** @var Expectation<string> $this */
    assertScorerResult(new Factuality(), $this->value, $threshold, $expected);

    return $this;
});

expect()->extend('toPassJudge', function (string $criteria, float $threshold = 0.7): Expectation {
    /** @var Expectation<string> $this */
    assertScorerResult(new LlmJudge(criteria: $criteria), $this->value, $threshold);

    return $this;
});

expect()->extend('toBeSemanticallySimilar', function (string $expected, float $threshold = 0.7): Expectation {
    /** @var Expectation<string> $this */
    assertScorerResult(new SemanticSimilarity(), $this->value, $threshold, $expected);

    return $this;
});

/**
 * @param  array<string, array<string, mixed>|Closure>  $expected
 */
expect()->extend('toHaveToolCalls', function (array $expected, float $threshold = 0.7): Expectation {
    /** @var Expectation<string> $this */
    /** @var array<string, array<string, mixed>|Closure> $expected */
    assertScorerResult(new ToolCallMatch(tools: $expected), $this->value, $threshold);

    return $this;
});

/**
 * @param  list<string>  $steps
 */
expect()->extend('toFollowTrajectory', function (array $steps, float $threshold = 0.7, bool $strictOrder = true): Expectation {
    /** @var Expectation<string> $this */
    /** @var list<string> $steps */
    assertScorerResult(new AgentTrajectory(sequence: $steps, strictOrder: $strictOrder), $this->value, $threshold);

    return $this;
});
