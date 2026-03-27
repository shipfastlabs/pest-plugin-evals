<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

final class EvalReport
{
    private static ?self $instance = null;

    /** @var list<array{agent: string, result: EvalResult}> */
    private array $entries = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function flush(): void
    {
        self::$instance = null;
    }

    public function add(string $agent, EvalResult $result): void
    {
        $this->entries[] = ['agent' => $agent, 'result' => $result];
    }

    public function totalEvals(): int
    {
        return count($this->entries);
    }

    public function passedEvals(): int
    {
        return collect($this->entries)->filter(fn (array $entry): bool => $entry['result']->passed)->count();
    }

    public function avgScore(): float
    {
        return collect($this->entries)->avg(fn (array $entry): float => $entry['result']->avgScore()) ?? 0.0;
    }

    public function totalCost(): CostSummary
    {
        return collect($this->entries)->reduce(
            fn (CostSummary $carry, array $entry): CostSummary => $carry->add($entry['result']->cost),
            CostSummary::zero(),
        );
    }

    public function renderSummary(): string
    {
        if ($this->entries === []) {
            return '';
        }

        $passed = $this->passedEvals();
        $total = $this->totalEvals();
        $avgScore = number_format($this->avgScore(), 2);
        $totalCost = $this->totalCost();

        $lines = [''];

        foreach ($this->entries as $entry) {
            $agent = $entry['agent'];
            $result = $entry['result'];
            $icon = $result->passed ? '<fg=green>✓</>' : '<fg=red>✗</>';

            $scorerParts = [];
            foreach ($result->scoresByScorer() as $scorer => $score) {
                $scorerName = class_basename($scorer);
                $formatted = number_format($score, 2);
                $color = $score >= $result->threshold ? 'green' : 'red';
                $scorerParts[] = "<fg={$color}>{$scorerName} {$formatted}</>";
            }

            $lines[] = "  {$icon} <fg=white>{$agent}</>  ".implode('  ', $scorerParts);
        }

        $lines[] = '';

        $passColor = $passed === $total ? 'green' : 'yellow';
        $lines[] = "  <fg={$passColor};options=bold>{$passed}/{$total} evals passed</>  <fg=gray>avg score {$avgScore}</>";

        if ($totalCost->totalTokens() > 0) {
            $lines[] = '  <fg=gray>'.number_format($totalCost->totalTokens()).' tokens</>';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
