<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Filters;

use Pest\Contracts\TestCaseMethodFilter;
use Pest\Factories\TestCaseMethodFactory;
use ShipFastLabs\PestEval\Plugin;

/**
 * @internal
 */
final readonly class ExcludesEvalTestCaseMethodFilter implements TestCaseMethodFilter
{
    public function accept(TestCaseMethodFactory $factory): bool
    {
        if (Plugin::$evalMode || ($_SERVER['PEST_EVAL_MODE'] ?? $_ENV['PEST_EVAL_MODE'] ?? null) === '1') {
            return true;
        }

        return ! str_contains($factory->filename, DIRECTORY_SEPARATOR.'Evals'.DIRECTORY_SEPARATOR);
    }
}
