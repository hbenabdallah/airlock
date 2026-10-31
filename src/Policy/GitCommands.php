<?php

declare(strict_types=1);

namespace App\Policy;

use App\Shell\ShellParser;

/**
 * Reads a git command line the way git does: past its own options, through its aliases.
 */
final readonly class GitCommands
{
    /** Git options that take the next word as their value. */
    private const array OPTIONS_WITH_VALUE = ['-C', '-c', '--git-dir', '--work-tree', '--namespace', '--exec-path', '--config-env'];

    /** Subcommands that talk to a remote; git never lets an alias stand for a subcommand it already has. */
    public const array REMOTE_SUBCOMMANDS = ['push', 'pull', 'fetch', 'clone', 'ls-remote', 'remote', 'submodule'];

    /** An alias may stand for another alias; git gives up on loops, and so does this. */
    private const int MAX_ALIAS_DEPTH = 5;

    public function __construct(
        private GitAliases $aliases,
        private ShellParser $parser,
    ) {
    }

    /**
     * @param list<string> $arguments what follows `git`
     */
    public function subcommand(array $arguments): ?GitSubcommand
    {
        $inlineAliases = [];
        while ([] !== $arguments && str_starts_with($arguments[0], '-')) {
            $option = array_shift($arguments);
            if (!\in_array($option, self::OPTIONS_WITH_VALUE, true)) {
                continue;
            }
            $value = array_shift($arguments) ?? '';
            if ('-c' === $option && 1 === preg_match('/^alias\.([^=]+)=(.*)$/s', $value, $match)) {
                $inlineAliases[$match[1]] = $match[2];
            }
        }

        $name = $arguments[0] ?? null;

        return null === $name ? null : $this->expand($name, $inlineAliases);
    }

    /**
     * @param array<string, string> $inlineAliases aliases given on the command line with -c
     */
    private function expand(string $name, array $inlineAliases): GitSubcommand
    {
        for ($depth = 0; $depth < self::MAX_ALIAS_DEPTH; ++$depth) {
            if (\in_array($name, self::REMOTE_SUBCOMMANDS, true)) {
                return new GitSubcommand($name);
            }
            $definition = $inlineAliases[$name] ?? $this->aliases->definition($name);
            if (null === $definition) {
                return new GitSubcommand($name);
            }
            if (str_starts_with(ltrim($definition), '!')) {
                return new GitSubcommand($name, isShellAlias: true);
            }
            $words = $this->parser->parse($definition)->commands[0]->words ?? [];
            $name = array_first(array_filter($words, static fn (string $word): bool => !str_starts_with($word, '-'))) ?? $name;
        }

        return new GitSubcommand($name);
    }
}
