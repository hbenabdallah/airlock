<?php

declare(strict_types=1);

namespace App\Output;

final readonly class Chunks
{
    /**
     * @param list<string> $chunks
     * @param int          $uncheckedBytes how much of the start of the text is in no chunk
     */
    public function __construct(
        public array $chunks,
        public int $uncheckedBytes = 0,
    ) {
    }
}
