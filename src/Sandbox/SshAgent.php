<?php

declare(strict_types=1);

namespace App\Sandbox;

/**
 * The user's SSH agent, lent to one command: it can sign with the keys, never read them.
 */
final readonly class SshAgent
{
    public function __construct(
        public string $socket,
    ) {
    }
}
