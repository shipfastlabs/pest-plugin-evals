# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A PestPHP plugin (`shipfastlabs/pest-plugin-eval`) for evaluating Laravel AI SDK agents. Provides a fluent API to run agents against prompts, score outputs with various scorers (deterministic, LLM-as-judge, semantic, tool-call), and assert pass rates.

## Commands

```bash
composer test          # Run all checks: rector dry-run, pint lint, phpstan, pest
composer test:unit     # Pest tests only
composer test:types    # PHPStan (max level)
composer test:lint     # Pint (PSR-12)
composer test:refactor # Rector dry-run
composer lint          # Fix code style with Pint
composer refactor      # Fix with Rector
```

Run a single test file:
```bash
./vendor/bin/pest tests/Unit/Scorers/JsonMatchTest.php
```

Run a single test by name:
```bash
./vendor/bin/pest --filter="test name here"
```

## Architecture

### Plugin Integration (src/Plugin.php)
Implements Pest's `HandlesArguments` and `AddsOutput` contracts. Key behavior:
- `pest --eval` targets `tests/Evals/` directory (or falls back to `--group=eval`)
- Regular `pest` runs auto-add `--exclude-group=eval` so evals never mix with normal tests
- After eval runs, renders `EvalReport` summary

### Eval Execution Flow
`EvalBuilder` (fluent API) -> `EvalRunner` -> `EvalResult`

1. **EvalBuilder** configures: agent class (or closure via `task()`), prompt, scorers, threshold, runs count, optional fake responses
2. **EvalRunner** executes the task N times, scores each run with all scorers, calculates pass rate
3. **EvalResult** holds runs, pass rate, and whether it passed the threshold
4. **EvalReport** is a singleton that collects results across tests for final output

Agents are resolved via Laravel Container (`Container::getInstance()->make()`). When `fake()` is used, the agent is bypassed entirely and predefined responses are returned.

### Scorer Interface
All scorers implement `Scorer::score(string $input, string $output, ?string $expected): ScorerResult`. Scores are 0.0-1.0. A run passes when its average scorer score >= threshold. Pass rate = fraction of runs that pass.

**Deterministic scorers** (no API calls): `ExactMatch`, `ContainsAll`, `ContainsAny`, `RegexMatch`, `JsonMatch`, `JsonSchemaMatch`

**LLM-based scorers** (use `JudgesWithLlm` trait to call Laravel AI SDK): `LlmJudge`, `Factuality`, `Relevance`, `Safety`. These send a structured prompt and parse a JSON `{score, reasoning}` response.

**Other scorers**: `SemanticSimilarity` (embedding cosine similarity), `ToolCallMatch` (validates tool calls/args), `AgentTrajectory` (validates tool call sequence)

### Global Functions (src/Autoload.php)
Registers `evaluate()`, `evaluateTask()`, and custom Pest expectations (`toPassScorer`, `toPassEval`, `toHaveAvgScore`). Also registers `InteractsWithEvals` trait via `Plugin::uses()`.

### Namespace
`ShipFastLabs\PestEval\` maps to `src/`. Tests use `ShipFastLabs\PestEval\Tests\`.

## Configuration

LLM scorer provider/model configured via `config/eval.php` (published from `src/Config/eval.php`). Defaults to OpenAI `gpt-4.1-mini` for scoring and `text-embedding-3-small` for embeddings. Override with env vars: `EVAL_SCORING_PROVIDER`, `EVAL_SCORING_MODEL`, `EVAL_EMBEDDING_PROVIDER`, `EVAL_EMBEDDING_MODEL`.

## CI

GitHub Actions runs tests on PHP 8.3 + 8.4, with both `prefer-lowest` and `prefer-stable` dependency strategies. Static analysis (PHPStan + Pint) runs separately on PHP 8.3.
