<?php

declare(strict_types=1);

namespace App\Grant;

use App\Shell\SimpleCommand;

/**
 * Lets one simple command run without asking: exactly these words, or these words followed by any others.
 */
final readonly class Grant
{
    /**
     * @param non-empty-list<string> $words
     */
    public function __construct(
        public array $words,
        public bool $coversLongerCommands,
    ) {
    }

    public function covers(SimpleCommand $command): bool
    {
        if (!$this->coversLongerCommands) {
            return $command->words === $this->words;
        }

        return \array_slice($command->words, 0, \count($this->words)) === $this->words;
    }

    /**
     * The grant as the user reads it: `make test …` for the prefix form, the command itself otherwise.
     */
    public function describe(): string
    {
        $command = implode(' ', array_map($this->quote(...), $this->words));

        return $this->coversLongerCommands ? $command.' …' : $command;
    }

    public function equals(self $other): bool
    {
        return $this->words === $other->words && $this->coversLongerCommands === $other->coversLongerCommands;
    }

    private function quote(string $word): string
    {
        if (1 === preg_match('#^[A-Za-z0-9_@%+=:,./-]+$#', $word)) {
            return $word;
        }

        return "'".str_replace("'", "'\\''", $word)."'";
    }
}
