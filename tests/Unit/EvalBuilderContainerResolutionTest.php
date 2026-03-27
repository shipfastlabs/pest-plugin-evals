<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use ShipFastLabs\PestEval\Eval\EvalBuilder;
use ShipFastLabs\PestEval\Scorers\ExactMatch;
use ShipFastLabs\PestEval\Tests\Fixtures\Support\ContainerGreeting;
use ShipFastLabs\PestEval\Tests\Fixtures\Support\ContainerResolvedPromptAgent;

it('resolves agents through the container', function (): void {
    $previousContainer = Container::getInstance();
    $container = new Container();
    $container->instance(ContainerGreeting::class, new ContainerGreeting('Hello'));
    Container::setInstance($container);

    try {
        $result = (new EvalBuilder())
            ->agent(ContainerResolvedPromptAgent::class)
            ->withPrompt('Taylor')
            ->expect('Hello Taylor')
            ->score(ExactMatch::class)
            ->run();

        expect($result->passed)->toBeTrue();
    } finally {
        Container::setInstance($previousContainer);
    }
});
