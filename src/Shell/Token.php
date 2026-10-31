<?php

declare(strict_types=1);

namespace App\Shell;

final readonly class Token
{
    /**
     * @param bool $isLiteral whether the word means the same whatever runs it: no variable, no substitution, no glob
     */
    public function __construct(
        public TokenType $type,
        public string $text,
        public bool $isLiteral = true,
    ) {
    }
}
