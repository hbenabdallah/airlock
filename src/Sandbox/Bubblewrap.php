<?php

declare(strict_types=1);

namespace App\Sandbox;

/**
 * Linux: the whole system mounted read-only, the writable paths bound back writable, and what must stay unread
 * covered by an empty, read-only mount.
 */
final readonly class Bubblewrap implements Sandbox
{
    public function __construct(
        private SandboxPaths $paths,
        private bool $cutsNetwork,
        private string $executable = 'bwrap',
    ) {
    }

    #[\Override]
    public function confine(array $command, string $projectDirectory): array
    {
        $arguments = [$this->executable, '--ro-bind', '/', '/', '--dev', '/dev', '--proc', '/proc'];
        foreach ([...$this->paths->writable(), $projectDirectory] as $writable) {
            $arguments[] = '--bind';
            $arguments[] = $writable;
            $arguments[] = $writable;
        }
        foreach ($this->paths->hidden() as $hidden) {
            array_push($arguments, ...$this->hide($hidden));
        }
        if ($this->cutsNetwork) {
            $arguments[] = '--unshare-net';
        }
        // --new-session: a command must not reach the terminal Airlock runs in (TIOCSTI).
        array_push($arguments, '--die-with-parent', '--new-session', '--chdir', $projectDirectory, '--', ...$command);

        return $arguments;
    }

    #[\Override]
    public function describe(): string
    {
        return \sprintf(
            'Sandbox (bubblewrap): writes only to the project, /tmp and package caches; your keys are hidden%s.',
            $this->cutsNetwork ? '; no network' : '',
        );
    }

    /**
     * @return list<string>
     */
    private function hide(string $path): array
    {
        if (is_dir($path)) {
            return ['--tmpfs', $path, '--remount-ro', $path];
        }

        return ['--ro-bind', '/dev/null', $path];
    }
}
