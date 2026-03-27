<?php

use ShipFastLabs\PestEval\Scorers\ContainsAny;

describe('ContainsAny', function (): void {
    it('scores 1.0 when any term is present', function (): void {
        $scorer = new ContainsAny(terms: ['refund', 'exchange', 'credit']);
        $result = $scorer->score('question', 'You can get a refund.');

        expect($result->score)->toBe(1.0);
    });

    it('scores 0.0 when no terms match', function (): void {
        $scorer = new ContainsAny(terms: ['xyz', 'abc']);
        $result = $scorer->score('question', 'hello world');

        expect($result->score)->toBe(0.0);
    });

    it('is case insensitive by default', function (): void {
        $scorer = new ContainsAny(terms: ['REFUND']);
        $result = $scorer->score('question', 'get a refund');

        expect($result->score)->toBe(1.0);
    });
});
