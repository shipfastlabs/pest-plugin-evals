<?php

use ShipFastLabs\PestEval\Scorers\RegexMatch;

describe('RegexMatch', function (): void {
    it('scores 1.0 when pattern matches', function (): void {
        $scorer = new RegexMatch(pattern: '/\d{3}-\d{4}/');
        $result = $scorer->score('question', 'Call us at 555-1234');

        expect($result->score)->toBe(1.0);
    });

    it('scores 0.0 when pattern does not match', function (): void {
        $scorer = new RegexMatch(pattern: '/\d{3}-\d{4}/');
        $result = $scorer->score('question', 'No phone number here');

        expect($result->score)->toBe(0.0);
    });

    it('scores 0.0 when no pattern provided', function (): void {
        $scorer = new RegexMatch();
        $result = $scorer->score('question', 'hello');

        expect($result->score)->toBe(0.0);
    });
});
