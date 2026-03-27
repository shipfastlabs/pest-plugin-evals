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

        [$outputDecoded, $outputError, $outputErrorMsg] = $this->decodeJson($output);

        [$expectedDecoded, $expectedError, $expectedErrorMsg] = $this->decodeJson($expected);

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

        $matches = $this->normalize($outputDecoded) === $this->normalize($expectedDecoded);

        return new ScorerResult(
            score: $matches ? 1.0 : 0.0,
            reasoning: $matches
                ? 'JSON structures match (order-insensitive).'
                : 'JSON structures differ.',
            scorer: self::class,
        );
    }

    /**
     * @return array{0: mixed, 1: int, 2: string}
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json);

        return [$decoded, json_last_error(), json_last_error_msg()];
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = $this->normalize($item);
            }

            return ['type' => 'array', 'value' => $normalized];
        }

        if (is_object($value)) {
            $normalized = [];

            foreach (get_object_vars($value) as $key => $item) {
                $normalized[$key] = $this->normalize($item);
            }

            ksort($normalized);

            return ['type' => 'object', 'value' => $normalized];
        }

        return $value;
    }
}
