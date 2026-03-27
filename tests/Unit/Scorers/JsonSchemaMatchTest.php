<?php

use ShipFastLabs\PestEval\Scorers\JsonSchemaMatch;

describe('JsonSchemaMatch', function (): void {
    it('scores 1.0 for valid schema match', function (): void {
        $scorer = new JsonSchemaMatch(schema: [
            'type' => 'object',
            'required' => ['name', 'age'],
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
        ]);

        $result = $scorer->score('question', '{"name": "John", "age": 30}');

        expect($result->score)->toBe(1.0);
    });

    it('scores 0.0 for missing required properties', function (): void {
        $scorer = new JsonSchemaMatch(schema: [
            'type' => 'object',
            'required' => ['name', 'age'],
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
        ]);

        $result = $scorer->score('question', '{"name": "John"}');

        expect($result->score)->toBe(0.0);
        expect($result->reasoning)->toContain('age');
    });

    it('scores 0.0 for wrong types', function (): void {
        $scorer = new JsonSchemaMatch(schema: [
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer'],
            ],
        ]);

        $result = $scorer->score('question', '{"age": "thirty"}');

        expect($result->score)->toBe(0.0);
    });

    it('scores 0.0 for invalid JSON', function (): void {
        $scorer = new JsonSchemaMatch(schema: ['type' => 'object']);
        $result = $scorer->score('question', 'not json');

        expect($result->score)->toBe(0.0);
    });

    it('rejects empty objects for array schemas', function (): void {
        $scorer = new JsonSchemaMatch(schema: ['type' => 'array']);
        $result = $scorer->score('question', '{}');

        expect($result->score)->toBe(0.0);
    });

    it('rejects empty arrays for object schemas', function (): void {
        $scorer = new JsonSchemaMatch(schema: ['type' => 'object']);
        $result = $scorer->score('question', '[]');

        expect($result->score)->toBe(0.0);
    });
});
