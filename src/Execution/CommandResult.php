<?php

declare(strict_types=1);

namespace App\Execution;

final readonly class CommandResult
{
    /**
     * @param int|null $exitCode null when the command was stopped before it exited
     * @param string   $output   standard output and error, interleaved as they were written
     */
    public function __construct(
        public ?int $exitCode,
        public string $output,
        public bool $hasTimedOut = false,
    ) {
    }

    public function isSuccessful(): bool
    {
        return 0 === $this->exitCode;
    }
}
