<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Tests\Fixtures\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class GreetingAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a friendly greeting assistant. When a user introduces themselves, greet them warmly by name. Keep responses brief and friendly.';
    }
}
