<?php

declare(strict_types=1);

namespace App\Output;

/**
 * What the output check found: clean, a likely injection, or nothing, and why.
 */
final readonly class OutputCheck
{
    /**
     * @param int $uncheckedBytes how much of the start of the output was too far from its end to be read
     */
    private function __construct(
        public OutputVerdict $verdict,
        public ?NotCheckedReason $reason = null,
        public int $uncheckedBytes = 0,
    ) {
    }

    public static function clean(int $uncheckedBytes = 0): self
    {
        return new self(OutputVerdict::Clean, uncheckedBytes: $uncheckedBytes);
    }

    public static function injection(int $uncheckedBytes = 0): self
    {
        return new self(OutputVerdict::Injection, uncheckedBytes: $uncheckedBytes);
    }

    public static function notChecked(NotCheckedReason $reason): self
    {
        return new self(OutputVerdict::NotChecked, $reason);
    }

    public function isInjection(): bool
    {
        return OutputVerdict::Injection === $this->verdict;
    }
}
