<?php

declare(strict_types=1);

namespace App\Output;

final readonly class MaskedText
{
    public function __construct(
        public string $text,
        public int $maskedSecrets,
    ) {
    }
}
