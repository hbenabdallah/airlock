<?php

declare(strict_types=1);

namespace App\Policy;

final readonly class RefusedCommand
{
    /**
     * @param non-empty-list<string> $words  a part of a line that starts with these words is refused
     * @param string                 $source the file that refuses it
     */
    public function __construct(
        public array $words,
        public string $source,
    ) {
    }
}
