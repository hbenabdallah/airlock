<?php

declare(strict_types=1);

namespace App\Policy;

use App\Execution\ProjectDirectory;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Process\Process;

/**
 * The aliases git would expand in the project, read with git itself: from the project's, the user's and the system's
 * configuration, includes followed.
 */
#[AsAlias(GitAliases::class)]
final readonly class ProjectGitAliases implements GitAliases
{
    public function __construct(
        private ProjectDirectory $projectDirectory,
    ) {
    }

    #[\Override]
    public function definition(string $alias): ?string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*$/', $alias)) {
            return null;
        }

        $process = new Process(['git', 'config', '--get', 'alias.'.$alias], cwd: $this->projectDirectory->path, timeout: 5);
        $process->run();

        return $process->isSuccessful() ? rtrim($process->getOutput(), "\n") : null;
    }
}
