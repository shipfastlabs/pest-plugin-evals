<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

use ShipFastLabs\PestEval\Scorers\ScorerResult;

final readonly class RunResult
{
    /**
     * @param  list<ScorerResult>  $scorerResults
     */
    public function __construct(
        public string $input,
        public string $output,
        public array $scorerResults,
        public float $latencyMs,
    ) {
    }

    public function avgScore(): float
    {
        return collect($this->scorerResults)->avg('score') ?? 0.0;
    }

    public function passed(float $threshold = 0.7): bool
    {
        return $this->avgScore() >= $threshold;
    }
}
