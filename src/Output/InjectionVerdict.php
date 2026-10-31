<?php

declare(strict_types=1);

namespace App\Output;

/**
 * The model's answer, as structured output: a field it must fill with one of two words, not prose to parse.
 */
final readonly class InjectionVerdict
{
    public function __construct(
        public InjectionLabel $verdict,
    ) {
    }
}
