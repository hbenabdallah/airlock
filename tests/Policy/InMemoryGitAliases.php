<?php

declare(strict_types=1);

namespace App\Tests\Policy;

use App\Policy\GitAliases;

final readonly class InMemoryGitAliases implements GitAliases
{
    /**
     * @param array<string, string> $definitions
     */
    public function __construct(
        private array $definitions = [],
    ) {
    }

    #[\Override]
    public function definition(string $alias): ?string
    {
        return $this->definitions[$alias] ?? null;
    }
}
