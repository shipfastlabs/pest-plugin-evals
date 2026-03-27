<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

use Illuminate\Support\Str;

final readonly class ExactMatch implements Scorer
{
    public function __construct(
        private bool $caseSensitive = true,
        private bool $trim = true,
    ) {
    }

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        if ($expected === null) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'No expected output provided for exact match comparison.',
                scorer: self::class,
            );
        }

        $actual = $this->trim ? trim($output) : $output;
        $reference = $this->trim ? trim($expected) : $expected;

        if (! $this->caseSensitive) {
            $actual = Str::lower($actual);
            $reference = Str::lower($reference);
        }

        $matches = $actual === $reference;

        return new ScorerResult(
            score: $matches ? 1.0 : 0.0,
            reasoning: $matches
                ? 'Output exactly matches expected.'
                : 'Output does not match expected. Got: "'.Str::limit($actual, 100).'"',
            scorer: self::class,
        );
    }
}
