<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * The command a client runs to start Airlock's MCP server.
 */
final readonly class ServerCommand
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        public string $command,
        public array $arguments,
    ) {
    }
}
