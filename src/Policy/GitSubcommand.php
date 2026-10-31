<?php

declare(strict_types=1);

namespace App\Policy;

/**
 * The subcommand git will run, once its aliases are expanded.
 */
final readonly class GitSubcommand
{
    /**
     * @param bool $isShellAlias whether it is an alias that runs a shell command (`!...`) rather than a subcommand
     */
    public function __construct(
        public string $name,
        public bool $isShellAlias = false,
    ) {
    }
}
