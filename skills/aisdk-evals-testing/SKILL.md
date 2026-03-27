---
name: aisdk-evals-testing
description: "Write evaluation tests for Laravel AI SDK agents using pest-plugin-eval. Use this skill whenever the user wants to evaluate AI agents, write eval tests, choose scorers (deterministic, LLM-as-judge, semantic, tool/agent), create custom scorers, configure eval scoring providers, or run evals with PestPHP. Triggers on: evaluate(), evaluateTask(), LlmJudge, ContainsAll, ExactMatch, Factuality, Relevance, Safety, SemanticSimilarity, ToolCallMatch, AgentTrajectory, pest --eval, make:eval, make:scorer, or any mention of AI agent evaluation/testing."
---

# Writing Evals with Pest Plugin Eval

## Overview

`pest-plugin-eval` is a PestPHP plugin for evaluating Laravel AI SDK agents. It provides a fluent API with 13 built-in scorers across deterministic, LLM-as-judge, semantic, and tool/agent categories.

Namespace: `ShipFastLabs\PestEval`
Entry points:
- `evaluate(AgentClass::class)` — evaluate an agent class (must have a `prompt($input)` method)
- `evaluateTask(fn(string $input): string => ...)` — evaluate any callable

```php
use function ShipFastLabs\PestEval\evaluate;
use ShipFastLabs\PestEval\Scorers\LlmJudge;
use ShipFastLabs\PestEval\Scorers\ContainsAll;

it('answers refund questions accurately', function () {
    evaluate(RefundAgent::class)
        ->withPrompt('Can I return a damaged laptop?')
        ->score(LlmJudge::class, criteria: 'Response explains the refund policy clearly')
        ->score(ContainsAll::class, terms: ['refund', 'return', '30 days'])
        ->threshold(0.8)
        ->assert();
})->group('eval');
```

Run evals with `pest --eval` or `pest --group=eval`.

## Choosing the Right Scorer

Pick scorers based on what you need to verify. Combine multiple scorers for thorough coverage — deterministic scorers are free and instant, so use them for hard requirements alongside LLM-based scorers for qualitative checks.

You know the exact expected output:
- `ExactMatch` — strict string comparison (supports case/trim options)
- `JsonMatch` — structural JSON comparison (order-insensitive)

Output must contain specific content:
- `ContainsAll` — all listed terms must appear (scores proportionally: 2/3 terms = 0.67)
- `ContainsAny` — at least one term must appear
- `RegexMatch` — output must match a regex pattern

Output must conform to a structure:
- `JsonSchemaMatch` — validates JSON output against a schema (types, required fields)

You need subjective quality evaluation:
- `LlmJudge` — evaluate against custom plain-English criteria (most flexible)
- `Relevance` — is the response on-topic to the input?
- `Safety` — check for harmful, toxic, or unsafe content
- `Factuality` — fact-check output against a reference answer (requires `->expect()`)

Output should be semantically close but not exact:
- `SemanticSimilarity` — cosine similarity between embeddings (requires `->expect()`)

Agent must use specific tools:
- `ToolCallMatch` — validate expected tool calls and their arguments
- `AgentTrajectory` — validate tool call sequence/order

## Scorer Reference

All scorers live in `ShipFastLabs\PestEval\Scorers`. Scorers marked with `expect()` require `->expect('reference')` on the builder.

### Deterministic Scorers (no API calls)

ExactMatch — `expect()` required
```php
->expect('Paris')
->score(ExactMatch::class, caseSensitive: false, trim: true)
```
Score: 1.0 if match, 0.0 otherwise. Params: `caseSensitive` (default true), `trim` (default true).

ContainsAll
```php
->score(ContainsAll::class, terms: ['refund', 'return', '30 days'], caseSensitive: false)
```
Score: proportional (matched / total terms). Params: `terms` (array), `caseSensitive` (default false).

ContainsAny
```php
->score(ContainsAny::class, terms: ['yes', 'approved', 'confirmed'])
```
Score: 1.0 if any term present, 0.0 otherwise. Params: `terms` (array), `caseSensitive` (default false).

RegexMatch
```php
->score(RegexMatch::class, pattern: '/\d{1,2}\s*days/')
```
Score: 1.0 if pattern matches, 0.0 otherwise. Params: `pattern` (string, with delimiters).

JsonMatch — `expect()` required
```php
->expect('{"currency": "USD", "amount": 30}')
->score(JsonMatch::class)
```
Score: 1.0 if structures match (order-insensitive), 0.0 otherwise. No params.

JsonSchemaMatch
```php
->score(JsonSchemaMatch::class, schema: [
    'type' => 'object',
    'required' => ['amount', 'currency'],
    'properties' => [
        'amount' => ['type' => 'integer'],
        'currency' => ['type' => 'string'],
    ],
])
```
Score: 1.0 if valid, 0.0 otherwise. Supports: `type`, `required`, `properties`, `items`, nested schemas.

### LLM-as-Judge Scorers (API calls to scoring provider)

All accept optional `provider` and `model` params to override the config defaults.

LlmJudge
```php
->score(LlmJudge::class, criteria: 'Response is empathetic, accurate, and mentions the 30-day policy')
```
Score: 0.0-1.0. The `criteria` param is plain English describing what a good response looks like.

Factuality — `expect()` required
```php
->expect('The capital of France is Paris')
->score(Factuality::class)
```
Score: 1.0 (equal), 0.9 (approximately equal), 0.8 (superset), 0.6 (subset), 0.0 (disagreement).

Relevance
```php
->score(Relevance::class)
```
Score: 0.0-1.0. Checks if the response is on-topic relative to the input prompt.

Safety
```php
->score(Safety::class)
```
Score: 0.0-1.0. Checks for harmful, toxic, or unsafe content. 1.0 = completely safe.

### Semantic Scorer (embedding API call)

SemanticSimilarity — `expect()` required
```php
->expect('5 to 7 business days')
->score(SemanticSimilarity::class)
```
Score: 0.0-1.0 (cosine similarity between embeddings). Uses the embedding provider from config.

### Agent/Tool Scorers (no API calls)

ToolCallMatch
```php
->score(ToolCallMatch::class, tools: [
    'LookupOrder' => ['order_id' => '12345'],
    'SendEmail' => fn(array $args) => filter_var($args['to'] ?? '', FILTER_VALIDATE_EMAIL) !== false,
], strict: false)
```
Score: proportional (matched / total tools). `strict: true` requires exact argument match; `false` (default) does subset check. Arguments can be arrays (exact match) or closures (custom validation).

AgentTrajectory
```php
->score(AgentTrajectory::class, sequence: [
    'SearchDatabase',
    'AnalyzeResults',
    'GenerateReport',
], strictOrder: true)
```
Score: proportional (matched / total sequence items). `strictOrder: true` (default) requires exact order; `false` just checks all tools were called.

## EvalBuilder API

```php
evaluate(AgentClass::class)          // or evaluateTask(fn($input) => ...)
    ->withPrompt('user input')       // required: the input to send
    ->expect('reference answer')     // optional: needed by some scorers
    ->score(Scorer::class, ...args)  // add scorer (chain multiple)
    ->using($scorerInstance)         // add pre-instantiated scorer
    ->threshold(0.7)                 // pass threshold 0-1 (default 0.7)
    ->runs(3)                        // repeat N times (default 1)
    ->fake(['response1', 'response2']) // mock responses (cycles through)
    ->assert();                      // run + assert (or ->run() for EvalResult)
```

EvalResult properties: `passed`, `passRate`, `avgLatencyMs`, `runs[]`, `scorerResults[]`, `cost`
EvalResult methods: `avgScore()`, `scoresByScorer()`

### Custom Expectations

```php
// On EvalResult
expect($result)->toPassEval(threshold: 0.8);
expect($result)->toHaveAvgScore(0.7);

// On string output
expect($response)->toPassScorer(ContainsAll::class, terms: ['refund'], threshold: 0.7);
```

## Common Patterns

### Dataset-driven evals
```php
it('handles various scenarios', function (string $prompt, string $criteria) {
    evaluate(RefundAgent::class)
        ->withPrompt($prompt)
        ->score(LlmJudge::class, criteria: $criteria)
        ->assert();
})->with([
    ['Can I return after 60 days?', 'Explains the 30-day policy limit'],
    ['Item arrived broken', 'Shows empathy and offers replacement'],
])->group('eval');
```

### Statistical robustness
```php
evaluate(SalesCoach::class)
    ->withPrompt('How do I handle price objections?')
    ->score(LlmJudge::class, criteria: 'Provides actionable sales techniques')
    ->runs(5)
    ->threshold(0.8) // 80% of 5 runs must pass
    ->assert();
```

### Fast iteration with faked responses
```php
evaluate(RefundAgent::class)
    ->withPrompt('Test input')
    ->fake(['Our return policy allows returns within 30 days.'])
    ->score(ContainsAll::class, terms: ['30 days'])
    ->assert();
```

### Combining deterministic + LLM scorers
```php
evaluate(RefundAgent::class)
    ->withPrompt('Can I return a damaged laptop?')
    ->score(ContainsAll::class, terms: ['refund', 'return'])     // fast, free
    ->score(LlmJudge::class, criteria: 'Professional and empathetic tone') // nuanced
    ->score(Safety::class)                                        // guardrail
    ->threshold(0.8)
    ->assert();
```

### Custom task without an agent class
```php
use function ShipFastLabs\PestEval\evaluateTask;

evaluateTask(fn(string $input): string => MyService::handle($input))
    ->withPrompt('Hello')
    ->score(ContainsAll::class, terms: ['greeting'])
    ->assert();
```

## Configuration

Publish config: `php artisan vendor:publish --tag=eval-config`

```php
// config/eval.php
return [
    'ai' => [
        'scoring' => [
            'provider' => env('EVAL_SCORING_PROVIDER', 'openai'),
            'model' => env('EVAL_SCORING_MODEL', 'gpt-5.4-nano'),
        ],
        'embedding' => [
            'provider' => env('EVAL_EMBEDDING_PROVIDER', 'openai'),
            'model' => env('EVAL_EMBEDDING_MODEL', 'text-embedding-3-small'),
        ],
    ],
];
```

LLM-based scorers accept `provider` and `model` params to override per-scorer:
```php
->score(LlmJudge::class, criteria: '...', provider: 'anthropic', model: 'claude-sonnet-4-5-20250514')
```

## Artisan Commands

```bash
php artisan make:eval RefundAgent      # creates tests/Evals/RefundAgentEvalTest.php
php artisan make:scorer ToneChecker    # creates app/Scorers/ToneChecker.php
```

## Custom Scorers

Implement the `Scorer` interface:

```php
use ShipFastLabs\PestEval\Scorers\Scorer;
use ShipFastLabs\PestEval\Scorers\ScorerResult;

class ToneScorer implements Scorer
{
    public function __construct(private string $expectedTone = 'professional') {}

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        $score = str_contains(mb_strtolower($output), $this->expectedTone) ? 0.9 : 0.2;

        return new ScorerResult(
            score: $score,
            reasoning: $score > 0.5 ? 'Tone matches.' : 'Tone mismatch.',
            scorer: self::class,
        );
    }
}
```

Use it: `->score(ToneScorer::class, expectedTone: 'casual')` or `->using(new ToneScorer('casual'))`

## Common Pitfalls

- Always add `->group('eval')` so `pest --eval` picks up your tests
- `ExactMatch`, `JsonMatch`, `Factuality`, and `SemanticSimilarity` require `->expect()` — forgetting it gives a 0.0 score
- `LlmJudge` needs a non-empty `criteria:` parameter — be specific about what "good" looks like
- `ToolCallMatch` and `AgentTrajectory` expect the agent's output to contain JSON-formatted tool calls
- Agent classes must be compatible with Laravel AI SDK (implement the agent contract with a `prompt()` method)
- The config path is `eval.ai.scoring.*` (not `eval.judge.*`)
