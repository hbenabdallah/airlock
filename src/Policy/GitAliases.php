<?php

declare(strict_types=1);

namespace App\Policy;

interface GitAliases
{
    /**
     * What a git alias stands for in the project, or null when it is not one.
     */
    public function definition(string $alias): ?string;
}
