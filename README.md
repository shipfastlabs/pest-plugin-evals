# Pest Plugin Eval

A PestPHP plugin for evaluating Laravel AI SDK agents. Build evals with LLM-as-judge, semantic similarity, deterministic matchers, and tool call validation — all with a fluent, Pest-native API.

## Installation

```bash
composer require shipfastlabs/pest-plugin-eval --dev
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=eval-config
```

## Quick Start

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

Run your evals:

```bash
pest --group=eval
# or
pest --eval
```

## Scorers

### Deterministic (no API calls, instant)

| Scorer | Description |
|--------|-------------|
| `ExactMatch` | Exact string comparison (with trim/case options) |
| `ContainsAll` | All terms present (proportional scoring) |
| `ContainsAny` | At least one term present |
| `RegexMatch` | Regex pattern matching |
| `JsonMatch` | Structural JSON comparison (order-insensitive) |
| `JsonSchemaMatch` | Validates output against a JSON schema |

### LLM-as-Judge (uses Laravel AI SDK)

| Scorer | Description |
|--------|-------------|
| `LlmJudge` | Custom criteria evaluation with plain-English rules |
| `Factuality` | Fact-checks output against a reference answer |
| `Relevance` | Checks if response is on-topic |
| `Safety` | Evaluates for harmful or unsafe content |

### Semantic

| Scorer | Description |
|--------|-------------|
| `SemanticSimilarity` | Embedding cosine similarity against reference |

### Agent / Tool

| Scorer | Description |
|--------|-------------|
| `ToolCallMatch` | Validates expected tool calls and arguments |
| `AgentTrajectory` | Validates tool call sequence/order |

## Usage Examples

### Multiple scorers

```php
it('provides helpful refund info', function () {
    evaluate(RefundAgent::class)
        ->withPrompt('Can I return a damaged laptop?')
        ->score(LlmJudge::class, criteria: 'Professional and empathetic tone')
        ->score(ContainsAll::class, terms: ['refund', 'return', 'damaged'])
        ->score(Relevance::class)
        ->threshold(0.8)
        ->assert();
})->group('eval');
```

### Statistical robustness (multiple runs)

```php
it('consistently provides good advice', function () {
    evaluate(SalesCoach::class)
        ->withPrompt('How do I handle price objections?')
        ->score(LlmJudge::class, criteria: 'Provides actionable sales techniques')
        ->runs(5)
        ->threshold(0.8) // 80% of runs must pass
        ->assert();
})->group('eval');
```

### With datasets

```php
it('handles various scenarios', function (string $prompt, string $criteria) {
    evaluate(RefundAgent::class)
        ->withPrompt($prompt)
        ->score(LlmJudge::class, criteria: $criteria)
        ->assert();
})->with([
    ['Can I return after 60 days?', 'Explains the 30-day policy limit'],
    ['Item arrived broken', 'Shows empathy and offers replacement'],
    ['I changed my mind', 'Explains standard return process'],
])->group('eval');
```

### Faked mode (fast iteration, no API calls)

```php
it('eval pipeline works with faked responses', function () {
    evaluate(RefundAgent::class)
        ->withPrompt('Test input')
        ->fake(['Our return policy allows returns within 30 days.'])
        ->score(ContainsAll::class, terms: ['30 days'])
        ->assert();
})->group('eval');
```

### Expected output comparison

```php
it('produces the correct answer', function () {
    evaluate(MathAgent::class)
        ->withPrompt('What is 2 + 2?')
        ->expect('4')
        ->score(ExactMatch::class)
        ->assert();
})->group('eval');
```

### Tool call validation

```php
it('calls the right tools', function () {
    evaluate(SupportAgent::class)
        ->withPrompt('Check order status for #12345')
        ->score(ToolCallMatch::class, tools: [
            'LookupOrder' => ['order_id' => '12345'],
        ])
        ->assert();
})->group('eval');
```

### Agent trajectory

```php
it('follows the correct workflow', function () {
    evaluate(ResearchAgent::class)
        ->withPrompt('Analyze competitor pricing')
        ->score(AgentTrajectory::class, sequence: [
            'SearchDatabase',
            'AnalyzeResults',
            'GenerateReport',
        ])
        ->assert();
})->group('eval');
```

### Expectation API

```php
it('responds relevantly', function () {
    $result = evaluate(RefundAgent::class)
        ->withPrompt('What is your return policy?')
        ->score(LlmJudge::class, criteria: 'Accurate refund information')
        ->run();

    expect($result)->toPassEval(threshold: 0.8);
    expect($result->avgScore())->toBeGreaterThan(0.7);
});
```

### Custom task (without an Agent class)

```php
use function ShipFastLabs\PestEval\evaluateTask;

it('works with any callable', function () {
    evaluateTask(fn (string $input) => "Echo: {$input}")
        ->withPrompt('Hello')
        ->score(ContainsAll::class, terms: ['Echo', 'Hello'])
        ->assert();
})->group('eval');
```

## Custom Expectations

```php
// On string responses
expect($response)->toPassScorer(ContainsAll::class, terms: ['refund']);

// On EvalResult
expect($result)->toPassEval(threshold: 0.8);
expect($result)->toHaveAvgScore(0.7);
```

## Artisan Commands

```bash
# Scaffold a new eval test
php artisan make:eval RefundAgent

# Scaffold a custom scorer
php artisan make:scorer ToneChecker
```

## Configuration

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
        // Your scoring logic here
        return new ScorerResult(
            score: 0.9,
            reasoning: 'Tone matches expected style.',
            scorer: self::class,
        );
    }
}
```

## License

Pest Plugin Eval is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
