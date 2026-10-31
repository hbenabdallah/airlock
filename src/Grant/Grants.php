<?php

declare(strict_types=1);

namespace App\Grant;

use App\Shell\CommandLine;
use App\Shell\SimpleCommand;

/**
 * Whether the grants given so far, for this session or for the project, let a line run without asking.
 */
final readonly class Grants
{
    public function __construct(
        private SessionGrants $session,
        private ProjectGrants $project,
    ) {
    }

    /**
     * Every simple command of the line covered by a grant. Whether the line may be covered at all is the policy's
     * call: a line asked about every time never is.
     *
     * @throws InvalidGrantsException
     */
    public function covers(CommandLine $line): bool
    {
        if ([] === $line->commands) {
            return false;
        }
        $grants = [...$this->session->all(), ...$this->project->all()];

        return array_all($line->commands, static fn (SimpleCommand $command): bool => array_any($grants, static fn (Grant $grant): bool => $grant->covers($command)));
    }
}
