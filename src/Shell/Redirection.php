<?php

declare(strict_types=1);

namespace App\Shell;

final readonly class Redirection
{
    public function __construct(
        public string $operator,
        public string $target,
    ) {
    }
}
