<?php

use ShipFastLabs\PestEval\Scorers\ContainsAll;

describe('ContainsAll', function (): void {
    it('scores 1.0 when all terms are present', function (): void {
        $scorer = new ContainsAll(terms: ['refund', 'policy', '30 days']);
        $result = $scorer->score('question', 'Our refund policy allows returns within 30 days.');

        expect($result->score)->toBe(1.0);
    });

    it('scores proportionally for partial matches', function (): void {
        $scorer = new ContainsAll(terms: ['refund', 'policy', 'missing_term']);
        $result = $scorer->score('question', 'Our refund policy allows returns.');

        expect($result->score)->toBeGreaterThan(0.5);
        expect($result->score)->toBeLessThan(1.0);
    });

    it('scores 0.0 when no terms match', function (): void {
        $scorer = new ContainsAll(terms: ['xyz', 'abc']);
        $result = $scorer->score('question', 'hello world');

        expect($result->score)->toBe(0.0);
    });

    it('is case insensitive by default', function (): void {
        $scorer = new ContainsAll(terms: ['REFUND', 'Policy']);
        $result = $scorer->score('question', 'our refund policy');

        expect($result->score)->toBe(1.0);
    });

    it('supports case sensitive mode', function (): void {
        $scorer = new ContainsAll(terms: ['REFUND'], caseSensitive: true);
        $result = $scorer->score('question', 'our refund policy');

        expect($result->score)->toBe(0.0);
    });

    it('scores 0.0 when no terms provided', function (): void {
        $scorer = new ContainsAll();
        $result = $scorer->score('question', 'hello world');

        expect($result->score)->toBe(0.0);
    });
});
