<?php

declare(strict_types=1);

namespace ShipFastLabs\PestEval\Eval;

final readonly class CostSummary
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public static function zero(): self
    {
        return new self();
    }

    public function add(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
        );
    }
}
