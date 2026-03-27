<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

use Closure;
use Illuminate\Container\Container;
use PHPUnit\Framework\Assert;
use RuntimeException;
use ShipFastLabs\PestEval\Scorers\Scorer;

final class EvalBuilder
{
    private ?string $agentClass = null;

    private ?Closure $task = null;

    private ?string $prompt = null;

    private ?string $expected = null;

    /** @var list<Scorer> */
    private array $scorers = [];

    private float $threshold = 0.7;

    private int $runs = 1;

    /** @var list<string> */
    private array $fakedResponses = [];

    public function agent(string $agentClass): self
    {
        $this->agentClass = $agentClass;

        return $this;
    }

    public function task(Closure $task): self
    {
        $this->task = $task;

        return $this;
    }

    public function withPrompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function expect(string $expected): self
    {
        $this->expected = $expected;

        return $this;
    }

    public function score(string $scorerClass, mixed ...$args): self
    {
        $scorer = new $scorerClass(...$args);

        if (! $scorer instanceof Scorer) {
            throw new RuntimeException("Class [{$scorerClass}] does not implement the Scorer interface.");
        }

        $this->scorers[] = $scorer;

        return $this;
    }

    public function using(Scorer $scorer): self
    {
        $this->scorers[] = $scorer;

        return $this;
    }

    public function threshold(float $threshold): self
    {
        $this->threshold = $threshold;

        return $this;
    }

    public function runs(int $runs): self
    {
        $this->runs = $runs;

        return $this;
    }

    /**
     * @param  list<string>  $responses
     */
    public function fake(array $responses = []): self
    {
        $this->fakedResponses = $responses;

        return $this;
    }

    public function run(): EvalResult
    {
        $task = $this->resolveTask();
        $runner = new EvalRunner();

        $result = $runner->run(
            task: $task,
            input: $this->prompt ?? '',
            scorers: $this->scorers,
            runs: $this->runs,
            threshold: $this->threshold,
            expected: $this->expected,
        );

        $agentName = $this->agentClass !== null ? class_basename($this->agentClass) : 'Task';
        EvalReport::instance()->add($agentName, $result);

        return $result;
    }

    public function assert(): EvalResult
    {
        $result = $this->run();

        if (! $result->passed) {
            $message = $this->buildFailureMessage($result);
            Assert::fail($message);
        }

        Assert::assertTrue($result->passed);

        return $result;
    }

    private function resolveTask(): Closure
    {
        if ($this->task instanceof Closure) {
            return $this->task;
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

        if ($this->agentClass !== null) {
            $agentClass = $this->agentClass;

            return function (string $input) use ($agentClass): string {
                $agent = Container::getInstance()->make($agentClass);

                return (string) $agent->prompt($input); // @phpstan-ignore method.nonObject, cast.string
            };
        }

        throw new RuntimeException('No agent or task configured. Use ->agent() or ->task() to set the eval target.');
    }

    private function buildFailureMessage(EvalResult $result): string
    {
        $lines = [
            "Eval failed: pass rate {$this->formatPercent($result->passRate)} below threshold {$this->formatPercent($result->threshold)}",
            '',
        ];

        foreach ($result->runs as $i => $run) {
            $status = $run->passed($this->threshold) ? 'PASS' : 'FAIL';
            $lines[] = "  Run #{$i}: [{$status}] avg score: {$this->formatScore($run->avgScore())}";

            foreach ($run->scorerResults as $scorerResult) {
                $scorerName = class_basename($scorerResult->scorer);
                $lines[] = "    {$scorerName}: {$this->formatScore($scorerResult->score)} - {$scorerResult->reasoning}";
            }
        }

        return implode("\n", $lines);
    }

    private function formatPercent(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }

    private function formatScore(float $value): string
    {
        return number_format($value, 2);
    }
}
