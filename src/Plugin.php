<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Container;
use Pest\TestSuite;
use ShipFastLabs\PestEval\Eval\EvalReport;
use ShipFastLabs\PestEval\Filters\ExcludesEvalTestCaseMethodFilter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements AddsOutput, Bootable, HandlesArguments
{
    public static bool $evalMode = false;

    public function boot(): void
    {
        TestSuite::getInstance()
            ->tests
            ->addTestCaseMethodFilter(new ExcludesEvalTestCaseMethodFilter());
    }

    public function handleArguments(array $arguments): array
    {
        if (in_array('--eval', $arguments, true)) {
            self::$evalMode = true;
            $_SERVER['PEST_EVAL_MODE'] = '1';
            $_ENV['PEST_EVAL_MODE'] = '1';
            putenv('PEST_EVAL_MODE=1');

            /** @var list<string> $filtered */
            $filtered = array_values(array_filter($arguments, fn (string $arg): bool => $arg !== '--eval'));

            if ($this->shouldTargetEvalDirectory($filtered)) {
                $filtered[] = 'tests/Evals';

                return $filtered;
            }

            if (! $this->hasGroupArgument($filtered)) {
                $filtered[] = '--group=eval';
            }

            return $filtered;
        }

        return $arguments;
    }

    public function addOutput(int $exitCode): int
    {
        if (self::$evalMode || ($_SERVER['PEST_EVAL_MODE'] ?? $_ENV['PEST_EVAL_MODE'] ?? null) === '1') {
            $report = EvalReport::instance();

            if ($report->totalEvals() > 0) {
                /** @var OutputInterface $output */
                $output = Container::getInstance()->get(OutputInterface::class);
                $output->writeln($report->renderSummary());
            }

            EvalReport::flush();
            self::$evalMode = false;
        }

        return $exitCode;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function shouldTargetEvalDirectory(array $arguments): bool
    {
        return is_dir('tests/Evals')
            && ! $this->hasPathArgument($arguments)
            && ! $this->hasGroupArgument($arguments);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasPathArgument(array $arguments): bool
    {
        foreach (array_slice($arguments, 1) as $argument) {
            if ($argument !== '' && ! str_starts_with($argument, '-')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function hasGroupArgument(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--group')) {
                return true;
            }
        }

        return false;
    }
}
