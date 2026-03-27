<?php

use ShipFastLabs\PestEval\Scorers\ExactMatch;

describe('ExactMatch', function (): void {
    it('scores 1.0 for exact match', function (): void {
        $scorer = new ExactMatch();
        $result = $scorer->score('question', 'hello world', 'hello world');

        expect($result->score)->toBe(1.0);
        expect($result->passed())->toBeTrue();
    });

    it('scores 0.0 for non-match', function (): void {
        $scorer = new ExactMatch();
        $result = $scorer->score('question', 'hello world', 'goodbye world');

        expect($result->score)->toBe(0.0);
        expect($result->passed())->toBeFalse();
    });

    it('handles case insensitive matching', function (): void {
        $scorer = new ExactMatch(caseSensitive: false);
        $result = $scorer->score('question', 'Hello World', 'hello world');

        expect($result->score)->toBe(1.0);
    });

    it('trims whitespace by default', function (): void {
        $scorer = new ExactMatch();
        $result = $scorer->score('question', '  hello world  ', 'hello world');

        expect($result->score)->toBe(1.0);
    });

    it('scores 0.0 when no expected provided', function (): void {
        $scorer = new ExactMatch();
        $result = $scorer->score('question', 'hello world');

        expect($result->score)->toBe(0.0);
    });
});
