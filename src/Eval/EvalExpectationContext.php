<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

use Closure;
use Illuminate\Container\Container;

final class EvalExpectationContext
{
    public static ?self $current = null;

    public static function currentPrompt(): string
    {
        return self::$current instanceof self ? self::$current->prompt : '';
    }

    public static function currentAgentName(): string
    {
        return self::$current instanceof self ? self::$current->agentName : 'Direct';
    }

    /**
     * @param  list<string>  $fakedResponses
     */
    public function __construct(
        public readonly string $prompt,
        public readonly string $agentName,
        public readonly int $runs = 1,
        public readonly array $fakedResponses = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolveOutputs(string|Closure $agent): array
    {
        $task = $this->resolveTask($agent);

        $outputs = [];

        for ($i = 0; $i < $this->runs; $i++) {
            $outputs[] = $task($this->prompt);
        }

        return $outputs;
    }

    /**
     * @return Closure(string): string
     */
    private function resolveTask(string|Closure $agent): Closure
    {
        if ($agent instanceof Closure) {
            return $agent;
        }

        if ($this->fakedResponses !== []) {
            $responses = $this->fakedResponses;
            $index = 0;

            return function (string $input) use ($responses, &$index): string {
                $response = $responses[$index] ?? $responses[array_key_last($responses)];
                $index++;

                return $response;
            };
        }

        return function (string $input) use ($agent): string {
            $instance = Container::getInstance()->make($agent);

            return (string) $instance->prompt($input); // @phpstan-ignore method.nonObject, cast.string
        };
    }
}
