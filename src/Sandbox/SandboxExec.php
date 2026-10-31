<?php

declare(strict_types=1);

namespace App\Sandbox;

/**
 * macOS: a Seatbelt profile that allows everything but writing outside the writable paths, reading what must stay
 * unread and, when the network is cut, any network but local sockets.
 */
final readonly class SandboxExec implements Sandbox
{
    /** What macOS itself writes on behalf of any program. */
    private const array SYSTEM_WRITABLE = ['/private/var/folders', '/dev'];

    public function __construct(
        private SandboxPaths $paths,
        private bool $cutsNetwork,
    ) {
    }

    #[\Override]
    public function confine(array $command, string $projectDirectory): array
    {
        return ['sandbox-exec', '-p', $this->profile($projectDirectory), ...$command];
    }

    #[\Override]
    public function describe(): string
    {
        return \sprintf(
            'Sandbox (sandbox-exec): writes only to the project, /tmp and package caches; your keys are hidden%s.',
            $this->cutsNetwork ? '; no network' : '',
        );
    }

    private function profile(string $projectDirectory): string
    {
        $writable = array_map(
            fn (string $path): string => \sprintf('(subpath %s)', $this->quote($path)),
            [...$this->paths->writable(), $projectDirectory, ...self::SYSTEM_WRITABLE],
        );
        $rules = ['(version 1)', '(allow default)', '(deny file-write*)', '(allow file-write* '.implode(' ', $writable).')'];
        foreach ($this->paths->hidden() as $hidden) {
            $rules[] = \sprintf('(deny file-read* (subpath %s))', $this->quote($hidden));
        }
        if ($this->cutsNetwork) {
            $rules[] = '(deny network*)';
            $rules[] = '(allow network* (local unix))';
        }

        return implode("\n", $rules)."\n";
    }

    private function quote(string $path): string
    {
        return '"'.addcslashes($path, '"\\').'"';
    }
}
