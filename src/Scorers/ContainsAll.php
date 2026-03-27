<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

use Illuminate\Support\Str;

final readonly class ContainsAll implements Scorer
{
    /**
     * @param  list<string>  $terms
     */
    public function __construct(
        private array $terms = [],
        private bool $caseSensitive = false,
    ) {
    }

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        if ($this->terms === []) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'No terms provided to check.',
                scorer: self::class,
            );
        }

        $haystack = $this->caseSensitive ? $output : Str::lower($output);
        $matched = [];
        $missing = [];

        foreach ($this->terms as $term) {
            $needle = $this->caseSensitive ? $term : Str::lower($term);

            if (str_contains($haystack, $needle)) {
                $matched[] = $term;
            } else {
                $missing[] = $term;
            }
        }

        $score = count($matched) / count($this->terms);

        return new ScorerResult(
            score: $score,
            reasoning: $missing === []
                ? 'All terms found in output.'
                : 'Missing terms: '.implode(', ', $missing).'. Found: '.implode(', ', $matched).'.',
            scorer: self::class,
        );
    }
}
