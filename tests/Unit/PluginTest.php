<?php

declare(strict_types=1);

use ShipFastLabs\PestEval\Plugin;
use ShipFastLabs\PestEval\Eval\EvalReport;

describe('Plugin --eval handling', function (): void {
    it('falls back to the eval group when tests/Evals is absent', function (): void {
        withTemporaryWorkingDirectory(function (): void {
            EvalReport::flush();
            $plugin = new Plugin();

            expect($plugin->handleArguments(['--eval']))->toBe(['--group=eval']);
            expect($plugin->addOutput(0))->toBe(0);
        });
    });

    it('targets tests/Evals when the eval directory exists', function (): void {
        withTemporaryWorkingDirectory(function (string $directory): void {
            mkdir("{$directory}/tests/Evals", 0777, true);

            EvalReport::flush();
            $plugin = new Plugin();

            expect($plugin->handleArguments(['--eval']))->toBe(['tests/Evals']);
            expect($plugin->addOutput(0))->toBe(0);
        });
    });

    it('excludes the eval group when --eval is not passed', function (): void {
        withTemporaryWorkingDirectory(function (): void {
            $plugin = new Plugin();

            expect($plugin->handleArguments([]))->toBe(['--exclude-group=eval']);
        });
    });

    it('does not add --exclude-group=eval when already present', function (): void {
        withTemporaryWorkingDirectory(function (): void {
            $plugin = new Plugin();

            expect($plugin->handleArguments(['--exclude-group=eval']))
                ->toBe(['--exclude-group=eval']);
        });
    });

    it('prefers the eval group when explicit paths are provided', function (): void {
        withTemporaryWorkingDirectory(function (string $directory): void {
            mkdir("{$directory}/tests/Evals", 0777, true);

            EvalReport::flush();
            $plugin = new Plugin();

            expect($plugin->handleArguments(['--eval', 'tests/Feature/ExampleTest.php']))
                ->toBe(['tests/Feature/ExampleTest.php', '--group=eval']);
            expect($plugin->addOutput(0))->toBe(0);
        });
    });
});

/**
 * @param  callable(string): void  $callback
 */
function withTemporaryWorkingDirectory(callable $callback): void
{
    $originalDirectory = getcwd();
    $temporaryDirectory = sys_get_temp_dir().'/pest-plugin-evals-'.uniqid('', true);

    mkdir($temporaryDirectory, 0777, true);
    chdir($temporaryDirectory);

    try {
        $callback($temporaryDirectory);
    } finally {
        chdir($originalDirectory);

        if (is_dir("{$temporaryDirectory}/tests/Evals")) {
            rmdir("{$temporaryDirectory}/tests/Evals");
        }

        if (is_dir("{$temporaryDirectory}/tests")) {
            rmdir("{$temporaryDirectory}/tests");
        }

        rmdir($temporaryDirectory);
    }
}
