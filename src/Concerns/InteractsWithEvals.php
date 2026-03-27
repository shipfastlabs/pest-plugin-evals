<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Concerns;

use Closure;
use ShipFastLabs\PestEval\Eval\EvalBuilder;

use function ShipFastLabs\PestEval\evaluate;
use function ShipFastLabs\PestEval\evaluateTask;

/**
 * @internal Used via Pest\Plugin::uses() in Autoload.php
 */
trait InteractsWithEvals // @phpstan-ignore trait.unused
{
    public function evaluate(string $agentClass): EvalBuilder
    {
        return evaluate($agentClass);
    }

    public function evaluateTask(Closure $task): EvalBuilder
    {
        return evaluateTask($task);
    }
}
