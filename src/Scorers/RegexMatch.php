<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

final readonly class RegexMatch implements Scorer
{
    public function __construct(
        private string $pattern = '',
    ) {
    }

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        if ($this->pattern === '') {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'No regex pattern provided.',
                scorer: self::class,
            );
        }

        $matches = preg_match($this->pattern, $output) === 1;

        return new ScorerResult(
            score: $matches ? 1.0 : 0.0,
            reasoning: $matches
                ? "Output matches pattern: {$this->pattern}"
                : "Output does not match pattern: {$this->pattern}",
            scorer: self::class,
        );
    }
}
