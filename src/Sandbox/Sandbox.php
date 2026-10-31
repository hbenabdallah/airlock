<?php

declare(strict_types=1);

namespace App\Sandbox;

/**
 * What a command runs inside.
 */
interface Sandbox
{
    /**
     * The command line that runs $command inside the sandbox, from $projectDirectory.
     *
     * @param non-empty-list<string> $command
     *
     * @return non-empty-list<string>
     */
    public function confine(array $command, string $projectDirectory): array;

    /**
     * One line saying what a command can do in it, or why there is none.
     */
    public function describe(): string;
}
