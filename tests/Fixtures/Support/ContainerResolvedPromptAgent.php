<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Tests\Fixtures\Support;

final readonly class ContainerResolvedPromptAgent
{
    public function __construct(
        private ContainerGreeting $greeting,
    ) {
    }

    public function prompt(string $input): string
    {
        return "{$this->greeting->prefix} {$input}";
    }
}
