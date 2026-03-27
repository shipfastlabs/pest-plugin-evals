<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Scorers;

final readonly class JsonSchemaMatch implements Scorer
{
    /**
     * @param  array<string, mixed>  $schema  JSON Schema definition
     */
    public function __construct(
        private array $schema = [],
    ) {
    }

    public function score(string $input, string $output, ?string $expected = null): ScorerResult
    {
        if ($this->schema === []) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'No JSON schema provided.',
                scorer: self::class,
            );
        }

        $decoded = json_decode($output);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new ScorerResult(
                score: 0.0,
                reasoning: 'Output is not valid JSON: '.json_last_error_msg(),
                scorer: self::class,
            );
        }

        $errors = $this->validate($decoded, $this->schema);

        if ($errors === []) {
            return new ScorerResult(
                score: 1.0,
                reasoning: 'Output matches the JSON schema.',
                scorer: self::class,
            );
        }

        return new ScorerResult(
            score: 0.0,
            reasoning: 'Schema validation errors: '.implode('; ', $errors),
            scorer: self::class,
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function validate(mixed $data, array $schema, string $path = '$'): array
    {
        $errors = [];

        if (isset($schema['type']) && is_string($schema['type'])) {
            $typeValid = match ($schema['type']) {
                'object' => is_object($data),
                'array' => is_array($data),
                'string' => is_string($data),
                'number' => is_int($data) || is_float($data),
                'integer' => is_int($data),
                'boolean' => is_bool($data),
                'null' => $data === null,
                default => true,
            };

            if (! $typeValid) {
                $errors[] = "{$path}: expected type '{$schema['type']}', got ".get_debug_type($data);

                return $errors;
            }
        }

        $properties = is_object($data) ? get_object_vars($data) : null;

        if (isset($schema['required']) && is_array($schema['required']) && is_array($properties)) {
            foreach ($schema['required'] as $required) {
                if (is_string($required) && ! array_key_exists($required, $properties)) {
                    $errors[] = "{$path}: missing required property '{$required}'";
                }
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties']) && is_array($properties)) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                /** @var array<string, mixed> $validSchema */
                $validSchema = is_array($propertySchema) ? $propertySchema : [];

                if (is_string($property) && array_key_exists($property, $properties)) {
                    $errors = [...$errors, ...$this->validate($properties[$property], $validSchema, "{$path}.{$property}")];
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items']) && is_array($data) && array_is_list($data)) {
            /** @var array<string, mixed> $itemsSchema */
            $itemsSchema = $schema['items'];

            foreach ($data as $index => $item) {
                $errors = [...$errors, ...$this->validate($item, $itemsSchema, "{$path}[{$index}]")];
            }
        }

        return $errors;
    }
}
