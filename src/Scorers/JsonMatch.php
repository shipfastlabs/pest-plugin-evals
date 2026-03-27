<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

final readonly class JsonMatch implements Scorer
{
    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        if ($expected === null) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'No expected JSON provided for comparison.',
                scorer: self::class,
            );
        }

        $outputDecoded = json_decode($output, true);
        $outputError = json_last_error();
        $outputErrorMsg = json_last_error_msg();

        $expectedDecoded = json_decode($expected, true);
        $expectedError = json_last_error();
        $expectedErrorMsg = json_last_error_msg();

        if ($outputDecoded === null && $outputError !== JSON_ERROR_NONE) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'Output is not valid JSON: '.$outputErrorMsg,
                scorer: self::class,
            );
        }

        if ($expectedDecoded === null && $expectedError !== JSON_ERROR_NONE) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'Expected value is not valid JSON: '.$expectedErrorMsg,
                scorer: self::class,
            );
        }

        $this->recursiveSort($outputDecoded);
        $this->recursiveSort($expectedDecoded);

        $matches = $outputDecoded === $expectedDecoded;

        return new ScorerResult(
            score: $matches ? 1.0 : 0.0,
            reasoning: $matches
                ? 'JSON structures match (order-insensitive).'
                : 'JSON structures differ.',
            scorer: self::class,
        );
    }

    private function recursiveSort(mixed &$value): void
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                $this->recursiveSort($item);
            }

            if (! array_is_list($value)) {
                ksort($value);
            }
        }
    }

}
