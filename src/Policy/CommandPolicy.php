<?php

declare(strict_types=1);

namespace App\Policy;

use App\Shell\CommandLine;
use App\Shell\Opacity;
use App\Shell\ShellParser;
use App\Shell\SimpleCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Weighs each part of a command line: plain, asked about every time, or refused by the policy.
 */
final readonly class CommandPolicy
{
    /** Programs that run another program given among their arguments. */
    private const array WRAPPERS = [
        'command', 'builtin', 'exec', 'env', 'nice', 'nohup', 'time', 'timeout', 'stdbuf', 'ionice', 'xargs', 'watch',
        'unbuffer', 'setsid', 'chronic', 'flock',
    ];

    private const array OTHER_USER_RUNNERS = ['sudo', 'su', 'doas', 'pkexec', 'runuser'];

    private const array DATA_DESTROYERS = ['dd', 'shred', 'wipefs', 'fdisk', 'sfdisk', 'parted'];

    private const array SHELLS = ['sh', 'bash', 'zsh', 'dash', 'ksh', 'fish'];

    private const array INTERPRETERS = [...self::SHELLS, 'python', 'python3', 'perl', 'ruby', 'php', 'node'];

    private const array DOWNLOADERS = ['curl', 'wget', 'fetch'];

    /** Options with which an interpreter runs code given on its command line rather than what it reads. */
    private const array INLINE_CODE_OPTIONS = ['-c', '-e', '-r', '-m'];

    /** Git options that take the next word as their value. */
    private const array GIT_OPTIONS_WITH_VALUE = ['-C', '-c', '--git-dir', '--work-tree', '--namespace', '--exec-path'];

    /** Find actions that change files or run a command on each file found. */
    private const array FIND_ACTIONS = ['-delete', '-exec', '-execdir', '-ok', '-okdir'];

    /** @var list<list<string>> */
    private array $refusedCommands;

    /**
     * @param array<mixed> $refusedCommands command lines the project forbids: one matches a part of a line that starts
     *                                      with the same program and the same arguments
     */
    public function __construct(
        private ShellParser $parser,
        private SecretFiles $secretFiles,
        #[Autowire(env: 'json:AIRLOCK_REFUSE')]
        array $refusedCommands,
    ) {
        $this->refusedCommands = array_values(array_map($this->parseRefusedCommand(...), $refusedCommands));
    }

    public function assess(CommandLine $line): Assessment
    {
        $verdict = Verdict::Plain;
        $reasons = [];
        $isDownloadPiped = false;

        foreach ($line->commands as $command) {
            $isDownloadPiped = $command->readsPipe && $isDownloadPiped;
            foreach ($this->concerns($command, $isDownloadPiped) as [$concernVerdict, $reason]) {
                $reasons[] = \sprintf('`%s`: %s', implode(' ', $command->words), $reason);
                $verdict = $concernVerdict->isWorseThan($verdict) ? $concernVerdict : $verdict;
            }
            $isDownloadPiped = $isDownloadPiped || \in_array(basename($command->programWords()[0] ?? ''), self::DOWNLOADERS, true);
        }

        if ($line->opacity instanceof Opacity) {
            $reasons[] = $line->opacity->reason();
            $verdict = Verdict::AskEveryTime->isWorseThan($verdict) ? Verdict::AskEveryTime : $verdict;
        }

        return new Assessment($verdict, $reasons);
    }

    /**
     * @return list<array{Verdict, string}>
     */
    private function concerns(SimpleCommand $command, bool $isDownloadPiped): array
    {
        $concerns = [];
        $words = $command->programWords();

        $refused = $this->refusedCommandMatching($words);
        if (null !== $refused) {
            $concerns[] = [Verdict::Refused, \sprintf('the policy refuses `%s`', implode(' ', $refused))];
        }

        $danger = $this->danger($words, $command->readsPipe, $isDownloadPiped);
        if ($danger instanceof Danger) {
            $concerns[] = [Verdict::AskEveryTime, $danger->reason()];
        }

        foreach ($this->secretFilesNamed($command) as $secret) {
            $concerns[] = [Verdict::AskEveryTime, \sprintf('it names %s, which usually holds secrets', $secret)];
        }

        return $concerns;
    }

    /**
     * @param list<string> $words
     *
     * @return list<string>|null
     */
    private function refusedCommandMatching(array $words): ?array
    {
        foreach ($this->invocations($words) as $invocation) {
            foreach ($this->refusedCommands as $refused) {
                if ($this->startsWith($invocation, $refused)) {
                    return $refused;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $words
     * @param list<string> $prefix
     */
    private function startsWith(array $words, array $prefix): bool
    {
        if (\count($words) < \count($prefix) || [] === $prefix) {
            return false;
        }
        $words[0] = basename($words[0]);
        $prefix[0] = basename($prefix[0]);

        return \array_slice($words, 0, \count($prefix)) === $prefix;
    }

    /**
     * @param list<string> $words
     */
    private function danger(array $words, bool $readsPipe, bool $isDownloadPiped): ?Danger
    {
        foreach ($this->invocations($words) as $invocation) {
            $danger = $this->dangerOfProgram($invocation, $readsPipe, $isDownloadPiped);
            if ($danger instanceof Danger) {
                return $danger;
            }
        }

        return null;
    }

    /**
     * The command itself, and when its program only runs another, every way of reading which one: a wrapper's own
     * options can take values (timeout -s KILL 5 rm -rf x), so any later word may be the program it runs.
     *
     * @param list<string> $words
     *
     * @return list<list<string>>
     */
    private function invocations(array $words): array
    {
        if ([] === $words || !\in_array(basename($words[0]), self::WRAPPERS, true)) {
            return [$words];
        }

        $invocations = [$words];
        for ($start = 1, $count = \count($words); $start < $count; ++$start) {
            $invocations[] = \array_slice($words, $start);
        }

        return $invocations;
    }

    /**
     * @param list<string> $words
     */
    private function dangerOfProgram(array $words, bool $readsPipe, bool $isDownloadPiped): ?Danger
    {
        $program = basename($words[0] ?? '');
        $arguments = \array_slice($words, 1);

        return match (true) {
            \in_array($program, self::OTHER_USER_RUNNERS, true) => Danger::RunsAsAnotherUser,
            'rm' === $program && $this->hasFlag($arguments, 'rR', '--recursive') => Danger::DeletesRecursively,
            \in_array($program, self::DATA_DESTROYERS, true), str_starts_with($program, 'mkfs') => Danger::DestroysData,
            'find' === $program && [] !== array_intersect($arguments, self::FIND_ACTIONS) => Danger::ActsOnWhatItFinds,
            'git' === $program && 'push' === $this->gitSubcommand($arguments) => Danger::PushesToRemote,
            'eval' === $program, \in_array($program, self::SHELLS, true) && $this->hasFlag($arguments, 'c') => Danger::RunsAString,
            $readsPipe && \in_array($program, self::INTERPRETERS, true) && $this->readsItsInput($arguments) => $isDownloadPiped ? Danger::RunsADownload : Danger::RunsWhatIsPiped,
            default => null,
        };
    }

    /**
     * Whether a short flag among $letters is set, alone or bundled (-rf), or a long option among $longOptions.
     *
     * @param list<string> $arguments
     */
    private function hasFlag(array $arguments, string $letters, string ...$longOptions): bool
    {
        return array_any($arguments, static fn (string $argument): bool => \in_array($argument, $longOptions, true) || 1 === preg_match('/^-[A-Za-z]*['.$letters.'][A-Za-z]*$/', $argument));
    }

    /**
     * @param list<string> $arguments
     */
    private function gitSubcommand(array $arguments): ?string
    {
        while ([] !== $arguments && str_starts_with($arguments[0], '-')) {
            $option = array_shift($arguments);
            if (\in_array($option, self::GIT_OPTIONS_WITH_VALUE, true)) {
                array_shift($arguments);
            }
        }

        return $arguments[0] ?? null;
    }

    /**
     * Whether an interpreter is given no script nor inline code, and so runs what it reads.
     *
     * @param list<string> $arguments
     */
    private function readsItsInput(array $arguments): bool
    {
        foreach ($arguments as $argument) {
            if (\in_array($argument, self::INLINE_CODE_OPTIONS, true)) {
                return false;
            }
            if ('-' !== $argument && !str_starts_with($argument, '-')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function secretFilesNamed(SimpleCommand $command): array
    {
        $candidates = \array_slice($command->words, 1);
        foreach ($command->redirections as $redirection) {
            $candidates[] = $redirection->target;
        }

        $secrets = [];
        foreach ($candidates as $candidate) {
            // The value of an option or an assignment names a file too: --env-file=.env, FILE=id_rsa.
            $equals = strrpos($candidate, '=');
            $path = false === $equals ? $candidate : substr($candidate, $equals + 1);
            if ($this->secretFiles->isSecret($path)) {
                $secrets[] = $path;
            }
        }

        return array_values(array_unique($secrets));
    }

    /**
     * @return list<string>
     */
    private function parseRefusedCommand(mixed $refused): array
    {
        if (!\is_string($refused)) {
            throw new \InvalidArgumentException(\sprintf('AIRLOCK_REFUSE must be a JSON list of command lines, got a %s in it.', get_debug_type($refused)));
        }

        $line = $this->parser->parse($refused);
        if ($line->isOpaque() || 1 !== \count($line->commands) || [] === $line->commands[0]->programWords()) {
            throw new \InvalidArgumentException(\sprintf('AIRLOCK_REFUSE holds "%s", which is not one plain command.', $refused));
        }

        return $line->commands[0]->programWords();
    }
}
