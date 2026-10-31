<?php

declare(strict_types=1);

namespace App\Tests\Sandbox;

use Symfony\Component\Process\ExecutableFinder;

/**
 * A machine where no program is installed.
 */
final class NoExecutables extends ExecutableFinder
{
    /**
     * @param array<mixed> $extraDirs
     */
    #[\Override]
    public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
    {
        return null;
    }
}
