<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use ShipFastLabs\PestEval\Eval\EvalReport;

/**
 * @internal
 */
final class Plugin implements AddsOutput, HandlesArguments
{
    private static bool $evalMode = false;

    public function handleArguments(array $arguments): array
    {
        if (in_array('--eval', $arguments, true)) {
            self::$evalMode = true;

            /** @var list<string> $filtered */
            $filtered = array_values(array_filter($arguments, fn (string $arg): bool => $arg !== '--eval'));
            $filtered[] = 'tests/Evals';

            return $filtered;
        }

        return $arguments;
    }

    public function addOutput(int $exitCode): int
    {
        if (self::$evalMode) {
            $report = EvalReport::instance();

            if ($report->totalEvals() > 0) {
                echo $report->render();
            }

            EvalReport::flush();
        }

        return $exitCode;
    }
}
