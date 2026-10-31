<?php

declare(strict_types=1);

namespace App\Tests\Sandbox;

use App\Sandbox\NoSandbox;
use App\Sandbox\Sandbox;
use App\Sandbox\SandboxDetector;
use App\Sandbox\SandboxMode;
use App\Sandbox\SandboxPaths;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

final class SandboxDetectorTest extends TestCase
{
    public function testTurnedOffThereIsNoSandboxAndItSaysWhy(): void
    {
        $sandbox = $this->detect(SandboxMode::Off, 'Linux');

        self::assertInstanceOf(NoSandbox::class, $sandbox);
        self::assertStringContainsString('turned off with AIRLOCK_SANDBOX=off', $sandbox->describe());
        self::assertSame(['bash', '-c', 'ls'], $sandbox->confine(['bash', '-c', 'ls'], '/project'));
    }

    public function testOnASystemWithoutAKnownSandboxThereIsNoneAndItSaysWhy(): void
    {
        $sandbox = $this->detect(SandboxMode::Auto, 'Windows');

        self::assertInstanceOf(NoSandbox::class, $sandbox);
        self::assertStringContainsString('no sandbox is known on Windows', $sandbox->describe());
    }

    public function testWithoutBubblewrapInstalledThereIsNoneAndItSaysHowToGetOne(): void
    {
        $sandbox = new SandboxDetector(new SandboxPaths('/nonexistent', '/nonexistent'), SandboxMode::Auto, new NoExecutables(), 'Linux')->detect();

        self::assertInstanceOf(NoSandbox::class, $sandbox);
        self::assertStringContainsString('bubblewrap is not installed', $sandbox->describe());
    }

    private function detect(SandboxMode $mode, string $operatingSystemFamily): Sandbox
    {
        return new SandboxDetector(new SandboxPaths('/nonexistent', '/nonexistent'), $mode, new ExecutableFinder(), $operatingSystemFamily)->detect();
    }
}
