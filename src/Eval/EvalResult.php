<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

use ShipFastLabs\PestEval\Scorers\ScorerResult;

final readonly class EvalResult
{
    /**
     * @param  list<RunResult>  $runs
     * @param  array<string, list<ScorerResult>>  $scorerResults  Grouped by scorer class
     */
    public function __construct(
        public array $runs,
        public array $scorerResults,
        public float $passRate,
        public bool $passed,
        public float $threshold,
        public CostSummary $cost,
        public float $avgLatencyMs,
    ) {
    }

    public function avgScore(): float
    {
        return collect($this->runs)->avg(fn (RunResult $r): float => $r->avgScore()) ?? 0.0;
    }

    /**
     * @return array<string, float> Average score per scorer class
     */
    public function scoresByScorer(): array
    {
        $scores = [];

        foreach ($this->scorerResults as $scorer => $results) {
            $scores[$scorer] = collect($results)->avg('score') ?? 0.0;
        }

        return $scores;
    }
}
