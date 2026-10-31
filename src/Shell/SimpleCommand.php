<?php

declare(strict_types=1);

namespace App\Shell;

/**
 * One program run with its arguments: what sits between two operators of a command line.
 */
final readonly class SimpleCommand
{
    /**
     * @param list<string>      $words        the program and its arguments, unquoted, variable assignments first
     * @param list<Redirection> $redirections
     * @param bool              $readsPipe    whether its input is the output of the command before it
     */
    public function __construct(
        public array $words,
        public array $redirections = [],
        public bool $readsPipe = false,
    ) {
    }

    /**
     * The words after the variables set for this command: its program first.
     *
     * @return list<string>
     */
    public function programWords(): array
    {
        $words = $this->words;
        while ([] !== $words && self::isAssignment($words[0])) {
            array_shift($words);
        }

        return $words;
    }

    public static function isAssignment(string $word): bool
    {
        return 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*\+?=/', $word);
    }
}
