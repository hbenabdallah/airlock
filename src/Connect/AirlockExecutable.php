<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * How this installation of Airlock is started: the binary itself, or PHP with this checkout's console.
 */
final readonly class AirlockExecutable
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDirectory,
    ) {
    }

    public function serverCommand(): ServerCommand
    {
        $archive = \extension_loaded('phar') ? \Phar::running(false) : '';
        if ('' !== $archive) {
            return new ServerCommand($archive, ['mcp:server']);
        }

        return new ServerCommand(\PHP_BINARY, [$this->projectDirectory.'/bin/console', 'mcp:server']);
    }
}
