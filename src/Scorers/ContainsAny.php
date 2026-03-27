<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

use Illuminate\Support\Str;

final readonly class ContainsAny implements Scorer
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

        foreach ($this->terms as $term) {
            $needle = $this->caseSensitive ? $term : Str::lower($term);

            if (str_contains($haystack, $needle)) {
                $matched[] = $term;
            }
        }

        $found = $matched !== [];

        return new ScorerResult(
            score: $found ? 1.0 : 0.0,
            reasoning: $found
                ? 'Found matching terms: '.implode(', ', $matched).'.'
                : 'None of the terms found in output: '.implode(', ', $this->terms).'.',
            scorer: self::class,
        );
    }
}
