<?php

declare(strict_types=1);

namespace App\Tests\Sandbox;

use App\Sandbox\SandboxExec;
use App\Sandbox\SandboxPaths;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The macOS profile, read rather than run: sandbox-exec only exists on macOS.
 */
final class SandboxExecTest extends TestCase
{
    private const string HOME = __DIR__.'/../../var/test-sandbox-exec';

    #[\Override]
    protected function setUp(): void
    {
        new Filesystem()->mkdir(self::HOME.'/.ssh');
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove(self::HOME);
    }

    public function testTheProfileDeniesWritesButToTheProjectAndHidesTheKeys(): void
    {
        $command = new SandboxExec(new SandboxPaths(self::HOME, '/nonexistent'), cutsNetwork: false)->confine(['bash', '-c', 'ls'], '/Users/me/project');

        self::assertSame(['sandbox-exec', '-p'], \array_slice($command, 0, 2));
        self::assertSame(['bash', '-c', 'ls'], \array_slice($command, 3));
        self::assertStringContainsString('(deny file-write*)', $command[2]);
        self::assertStringContainsString('(subpath "/Users/me/project")', $command[2]);
        self::assertStringContainsString(\sprintf('(deny file-read* (subpath "%s/.ssh"))', realpath(self::HOME)), $command[2]);
        self::assertStringNotContainsString('network', $command[2]);
    }

    public function testStrictCutsTheNetwork(): void
    {
        $command = new SandboxExec(new SandboxPaths(self::HOME, '/nonexistent'), cutsNetwork: true)->confine(['true'], '/p');

        self::assertStringContainsString('(deny network*)', $command[2]);
    }
}
