<?php

declare(strict_types=1);

namespace App\Execution;

/**
 * The end of what a command writes, kept while it runs: a command that writes without end must not fill Airlock's
 * memory. It keeps twice what the agent gets, so that masking a secret never depends on where the start was cut.
 */
final class OutputTail
{
    private string $kept = '';

    private int $droppedBytes = 0;

    /**
     * @param positive-int $keptBytes
     */
    public function __construct(
        private readonly int $keptBytes,
    ) {
    }

    public function append(string $chunk): void
    {
        $this->kept .= $chunk;
        $excess = \strlen($this->kept) - 2 * $this->keptBytes;
        if ($excess <= 0) {
            return;
        }

        $cut = $excess + $this->keptBytes;
        $this->kept = substr($this->kept, $cut);
        $this->droppedBytes += $cut;
    }

    public function text(): string
    {
        return $this->kept;
    }

    public function droppedBytes(): int
    {
        return $this->droppedBytes;
    }
}
