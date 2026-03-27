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

    public function render(): string
    {
        if ($this->entries === []) {
            return 'No eval results to report.';
        }

        $lines = [
            '',
            'Eval Report',
            str_repeat('─', 65),
            sprintf('  %-25s %-20s %-8s %s', 'Agent', 'Scorer', 'Score', 'Pass'),
            str_repeat('─', 65),
        ];

        foreach ($this->entries as $entry) {
            $agent = $entry['agent'];

            foreach ($entry['result']->scoresByScorer() as $scorer => $avgScore) {
                $scorerName = class_basename($scorer);
                $passed = $avgScore >= $entry['result']->threshold;

                $lines[] = sprintf(
                    '  %-25s %-20s %-8s %s',
                    $agent,
                    $scorerName,
                    number_format($avgScore, 2),
                    $passed ? 'PASS' : 'FAIL',
                );

                $agent = '';
            }
        }

        $totalCost = $this->totalCost();

        $lines[] = str_repeat('─', 65);
        $lines[] = sprintf(
            'Overall: %d/%d passed | Avg score: %s',
            $this->passedEvals(),
            $this->totalEvals(),
            number_format($this->avgScore(), 2),
        );

        if ($totalCost->totalTokens() > 0) {
            $lines[] = sprintf(
                'Cost: %s tokens',
                number_format($totalCost->totalTokens()),
            );
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
