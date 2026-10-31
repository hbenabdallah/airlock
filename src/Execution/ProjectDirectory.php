<?php

declare(strict_types=1);

namespace App\Execution;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The project commands run in: AIRLOCK_PROJECT_DIR, or the directory the client started Airlock from.
 */
final readonly class ProjectDirectory
{
    public string $path;

    public function __construct(
        #[Autowire(env: 'default::AIRLOCK_PROJECT_DIR')]
        ?string $configured = null,
    ) {
        $candidate = null === $configured || '' === $configured ? getcwd() : $configured;
        $path = false === $candidate ? false : realpath($candidate);
        if (false === $path || !is_dir($path)) {
            throw new \RuntimeException(\sprintf('The project directory "%s" does not exist.', $configured ?? '(the current directory)'));
        }

        $this->path = $path;
    }
}
