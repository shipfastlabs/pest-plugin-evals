<?php

use ShipFastLabs\PestEval\Scorers\JsonMatch;

describe('JsonMatch', function (): void {
    it('scores 1.0 for matching JSON', function (): void {
        $scorer = new JsonMatch();
        $result = $scorer->score(
            'question',
            '{"name": "John", "age": 30}',
            '{"age": 30, "name": "John"}',
        );

        expect($result->score)->toBe(1.0);
    });

    it('scores 0.0 for different JSON', function (): void {
        $scorer = new JsonMatch();
        $result = $scorer->score(
            'question',
            '{"name": "John", "age": 30}',
            '{"name": "Jane", "age": 25}',
        );

        expect($result->score)->toBe(0.0);
    });

    it('scores 0.0 for invalid JSON output', function (): void {
        $scorer = new JsonMatch();
        $result = $scorer->score('question', 'not json', '{"key": "value"}');

        expect($result->score)->toBe(0.0);
        expect($result->reasoning)->toContain('not valid JSON');
    });

    it('scores 0.0 when no expected provided', function (): void {
        $scorer = new JsonMatch();
        $result = $scorer->score('question', '{"key": "value"}');

        expect($result->score)->toBe(0.0);
    });

    it('handles nested JSON comparison', function (): void {
        $scorer = new JsonMatch();
        $result = $scorer->score(
            'question',
            '{"user": {"name": "John", "address": {"city": "NYC"}}}',
            '{"user": {"address": {"city": "NYC"}, "name": "John"}}',
        );

        expect($result->score)->toBe(1.0);
    });
});
