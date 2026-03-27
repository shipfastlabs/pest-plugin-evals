---
name: aisdk-evals-testing
description: "Write evaluation tests for Laravel AI SDK agents using pest-plugin-evals. Use this skill whenever the user wants to evaluate AI agents, write eval tests, use LLM-as-judge scoring, semantic similarity, tool/agent validation, or run evals with PestPHP. Triggers on: expectAgent(), toBeRelevant, toBeSafe, toBeFactual, toPassJudge, toBeSemanticallySimilar, toHaveToolCalls, toFollowTrajectory, LlmJudge, Factuality, Relevance, Safety, SemanticSimilarity, ToolCallMatch, AgentTrajectory, pest --eval, make:eval, make:scorer, or any mention of AI agent evaluation/testing."
---

# Writing Evals with Pest Plugin Eval

## Overview

`pest-plugin-evals` is a PestPHP plugin for evaluating Laravel AI SDK agents. It uses Pest's native `expect()` API — `expectAgent()` runs the agent and returns a standard Pest Expectation, so native Pest expectations work directly on agent output alongside custom LLM scorer expectations.

Namespace: `ShipFastLabs\PestEval`
Entry point: `expectAgent(AgentClass::class, 'prompt')` — runs the agent, returns Pest Expectation wrapping the output.

```php
use function ShipFastLabs\PestEval\expectAgent;

it('answers refund questions accurately', function () {
    expectAgent(RefundAgent::class, 'Can I return a damaged laptop?')
        ->toContain('refund')
        ->toContain('return')
        ->toPassJudge('Response explains the refund policy clearly', threshold: 0.8)
        ->toBeRelevant(0.8);
})->group('eval');
```

Run evals with `pest --eval`. Eval tests are **excluded from normal test runs** automatically — the plugin adds `--exclude-group=eval` when `--eval` is not passed.

## How It Works

`expectAgent()` resolves the agent, runs it with the prompt, and returns a Pest `Expectation<string>` wrapping the output. All native Pest expectations work directly. Custom expectations handle LLM scoring.

```php
expectAgent(MyAgent::class, 'What is the capital of France?')
    ->toBe('Paris')              // native Pest
    ->toContain('Paris')         // native Pest
    ->toMatch('/^[A-Z]/')        // native Pest
    ->toBeRelevant(0.9)          // custom LLM scorer
    ->toBeSafe();                // custom LLM scorer
```

For multiple runs: `expectAgent(Agent::class, 'prompt', runs: 5)` — every assertion must pass on every output.

## Choosing the Right Approach

**Deterministic checks — use native Pest expectations (free, instant):**
- `->toContain('term')` — output contains a string
- `->toMatch('/pattern/')` — regex match
- `->toBe('exact')` — exact string match
- `->toBeJson()` — valid JSON
- `->json()->toHaveKey('key')` — JSON structure

**Subjective quality — use custom LLM expectations:**
- `->toPassJudge('criteria', 0.8)` — evaluate against custom plain-English criteria (most flexible)
- `->toBeRelevant(0.7)` — is the response on-topic?
- `->toBeSafe(0.7)` — check for harmful content
- `->toBeFactual(0.7, expected: 'reference')` — fact-check against reference

**Semantic comparison:**
- `->toBeSemanticallySimilar('reference', 0.7)` — cosine similarity between embeddings

**Agent tool validation:**
- `->toHaveToolCalls([...])` — validate expected tool calls and arguments
- `->toFollowTrajectory([...])` — validate tool call sequence/order

## Custom Expectations Reference

All thresholds default to 0.7 and represent the minimum score (0.0-1.0) required to pass.

### LLM-as-Judge

toPassJudge
```php
->toPassJudge('Response is empathetic, accurate, and mentions the 30-day policy', threshold: 0.8)
```
Score: 0.0-1.0. The `criteria` param is plain English describing what a good response looks like.

toBeFactual — requires `expected:` reference
```php
->toBeFactual(expected: 'The capital of France is Paris')
```
Score: 1.0 (equal), 0.9 (approximately equal), 0.8 (superset), 0.6 (subset), 0.0 (disagreement).

toBeRelevant
```php
->toBeRelevant(0.8)
```
Score: 0.0-1.0. Checks if the response is on-topic relative to the input prompt.

toBeSafe
```php
->toBeSafe(0.9)
```
Score: 0.0-1.0. Checks for harmful, toxic, or unsafe content. 1.0 = completely safe.

### Semantic

toBeSemanticallySimilar — requires `expected` reference
```php
->toBeSemanticallySimilar('5 to 7 business days', threshold: 0.7)
```
Score: 0.0-1.0 (cosine similarity between embeddings).

### Agent/Tool

toHaveToolCalls
```php
->toHaveToolCalls([
    'LookupOrder' => ['order_id' => '12345'],
    'SendEmail' => fn(array $args) => filter_var($args['to'] ?? '', FILTER_VALIDATE_EMAIL) !== false,
])
```
Score: proportional (matched / total tools). Arguments can be arrays (subset match) or closures (custom validation).

toFollowTrajectory
```php
->toFollowTrajectory(['SearchDatabase', 'AnalyzeResults', 'GenerateReport'], strictOrder: true)
```
Score: proportional (matched / total sequence items). `strictOrder: true` (default) requires exact order.

## expectAgent() API

```php
expectAgent(
    string|Closure $agent,   // Agent class name or closure
    string $prompt,          // The input prompt
    int $runs = 1,           // Number of runs (all assertions checked on every output)
    array $fake = [],        // Fake responses (bypasses agent execution)
): mixed
```

## Common Patterns

### Dataset-driven evals
```php
it('handles various scenarios', function (string $prompt, string $criteria) {
    expectAgent(RefundAgent::class, $prompt)
        ->toPassJudge($criteria);
})->with([
    ['Can I return after 60 days?', 'Explains the 30-day policy limit'],
    ['Item arrived broken', 'Shows empathy and offers replacement'],
])->group('eval');
```

### Statistical robustness (multiple runs)
```php
it('consistently provides good advice', function () {
    expectAgent(SalesCoach::class, 'How do I handle price objections?', runs: 5)
        ->toContain('objection')
        ->toPassJudge('Provides actionable sales techniques');
})->group('eval');
```

### Fast iteration with faked responses
```php
it('eval pipeline works with faked responses', function () {
    expectAgent(
        RefundAgent::class,
        'What is your return policy?',
        fake: ['Our return policy allows returns within 30 days.'],
    )->toContain('30 days')
        ->toMatch('/\d+ days/');
})->group('eval');
```

### Combining native Pest + LLM expectations
```php
it('provides helpful refund info', function () {
    expectAgent(RefundAgent::class, 'Can I return a damaged laptop?')
        ->toContain('refund')
        ->toContain('return')
        ->toPassJudge('Professional and empathetic tone', threshold: 0.8)
        ->toBeSafe();
})->group('eval');
```

### Closure task (without an agent class)
```php
it('works with any callable', function () {
    expectAgent(
        fn(string $input): string => MyService::handle($input),
        'Hello',
    )->toContain('greeting');
})->group('eval');
```

### Direct mode (score an existing string)
```php
it('validates a pre-computed response', function () {
    expect('The capital of France is Paris.')
        ->toBeRelevant(0.8);
});
```

## Configuration

Publish config: `php artisan vendor:publish --tag=eval-config`

```php
// config/eval.php
return [
    'ai' => [
        'scoring' => [
            'provider' => env('EVAL_SCORING_PROVIDER', 'openai'),
            'model' => env('EVAL_SCORING_MODEL', 'gpt-4.1-mini'),
        ],
        'embedding' => [
            'provider' => env('EVAL_EMBEDDING_PROVIDER', 'openai'),
            'model' => env('EVAL_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
    ],
];
```

## Artisan Commands

```bash
php artisan make:eval RefundAgent      # creates tests/Evals/RefundAgentEvalTest.php
php artisan make:scorer ToneChecker    # creates app/Scorers/ToneChecker.php
```

## Custom Scorers

### 1. Create the scorer

Scaffold with artisan or implement the `Scorer` interface manually:

```bash
php artisan make:scorer ToneScorer
```

```php
namespace App\Scorers;

use ShipFastLabs\PestEval\Scorers\Scorer;
use ShipFastLabs\PestEval\Scorers\ScorerResult;

final class ToneScorer implements Scorer
{
    public function __construct(
        private string $expectedTone = 'professional',
    ) {}

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        $score = str_contains(mb_strtolower($output), $this->expectedTone) ? 1.0 : 0.0;

        return new ScorerResult(
            score: $score,
            reasoning: $score > 0.5 ? "Output matches '{$this->expectedTone}' tone." : "Output does not match '{$this->expectedTone}' tone.",
            scorer: self::class,
        );
    }
}
```

The `score()` method receives:
- `$input` — the prompt sent to the agent
- `$output` — the agent's response (this is what you score)
- `$expected` — optional reference answer (for comparison-based scorers)

Return a `ScorerResult` with a `score` between `0.0` (fail) and `1.0` (pass).

### 2. Register as a Pest expectation

Add a custom expectation in `tests/Pest.php` (or `tests/Expectations.php`):

```php
use App\Scorers\ToneScorer;
use Pest\Expectation;
use function ShipFastLabs\PestEval\assertScorerResult;

expect()->extend('toHaveTone', function (string $tone, float $threshold = 0.7): Expectation {
    assertScorerResult(new ToneScorer($tone), $this->value, $threshold);

    return $this;
});
```

`assertScorerResult()` handles context resolution, scoring, reporting to `EvalReport`, and asserting the score meets the threshold.

### 3. Use in eval tests

```php
it('responds professionally', function () {
    expectAgent(SupportAgent::class, 'I want a refund')
        ->toContain('refund')
        ->toHaveTone('professional', threshold: 0.8)
        ->toBeSafe();
})->group('eval');
```

## Common Pitfalls

- Always add `->group('eval')` so `pest --eval` picks up your tests
- `toPassJudge` needs specific `criteria:` — be clear about what "good" looks like
- `toBeFactual` and `toBeSemanticallySimilar` need an `expected:` reference string
- `toHaveToolCalls` and `toFollowTrajectory` expect JSON-formatted tool calls in the output
- Agent classes must have a `prompt()` method (Laravel AI SDK agent contract)
- The config path is `eval.ai.scoring.*` (not `eval.judge.*`)
