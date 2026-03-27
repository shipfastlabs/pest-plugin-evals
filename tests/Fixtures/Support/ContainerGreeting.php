<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Tests\Fixtures\Support;

final readonly class ContainerGreeting
{
    public function __construct(
        public string $prefix,
    ) {
    }
}
