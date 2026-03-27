<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

use Closure;
use ShipFastLabs\PestEval\Scorers\Scorer;
use ShipFastLabs\PestEval\Scorers\ScorerResult;

final class EvalRunner
{
    /**
     * @param  list<Scorer>  $scorers
     */
    public function run(
        Closure $task,
        string $input,
        array $scorers,
        int $runs = 1,
        float $threshold = 0.7,
        ?string $expected = null,
    ): EvalResult {
        $runResults = [];

        for ($i = 0; $i < $runs; $i++) {
            $startTime = hrtime(true);
            /** @var string $output */
            $output = $task($input);
            $output = (string) $output;
            $latencyMs = (hrtime(true) - $startTime) / 1_000_000;

            $scorerResults = array_map(
                fn (Scorer $scorer): ScorerResult => $scorer->score($input, $output, $expected),
                $scorers,
            );

            $runResults[] = new RunResult(
                input: $input,
                output: $output,
                scorerResults: $scorerResults,
                latencyMs: $latencyMs,
            );
        }

        $scorerGrouped = $this->groupByScorer($runResults);
        $results = collect($runResults);
        $passRate = $results->isEmpty() ? 0.0 : $results->filter(fn (RunResult $r): bool => $r->passed($threshold))->count() / $results->count();
        $avgLatency = $results->avg('latencyMs') ?? 0.0;

        return new EvalResult(
            runs: $runResults,
            scorerResults: $scorerGrouped,
            passRate: $passRate,
            passed: $passRate >= $threshold,
            threshold: $threshold,
            cost: CostSummary::zero(),
            avgLatencyMs: $avgLatency,
        );
    }

    /**
     * @param  list<RunResult>  $runs
     * @return array<string, list<ScorerResult>>
     */
    private function groupByScorer(array $runs): array
    {
        $grouped = [];

        foreach ($runs as $run) {
            foreach ($run->scorerResults as $result) {
                $grouped[$result->scorer][] = $result;
            }
        }

        return $grouped;
    }
}
