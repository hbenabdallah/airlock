<?php

declare(strict_types=1);

namespace App\Connect;

final readonly class ConnectionContext
{
    public function __construct(
        public string $projectDirectory,
        public string $home,
        public ServerCommand $server,
    ) {
    }
}
