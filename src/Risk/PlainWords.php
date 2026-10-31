<?php

declare(strict_types=1);

namespace App\Risk;

/**
 * The model's answer, as structured output: the line is a field, not prose to parse.
 */
final readonly class PlainWords
{
    public function __construct(
        public string $summary,
    ) {
    }
}
