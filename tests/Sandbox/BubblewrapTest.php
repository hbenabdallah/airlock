<?php

declare(strict_types=1);

namespace App\Tests\Sandbox;

use App\Execution\CommandResult;
use App\Execution\CommandRunner;
use App\Execution\ProjectDirectory;
use App\Sandbox\Bubblewrap;
use App\Sandbox\SandboxDetector;
use App\Sandbox\SandboxMode;
use App\Sandbox\SandboxPaths;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Real commands in real bubblewrap. Everything lives under var/, which is neither the project, /tmp nor a cache:
 * a write there is a write outside what the sandbox opens.
 */
#[RequiresOperatingSystemFamily('Linux')]
final class BubblewrapTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../var/test-sandbox';

    private Filesystem $filesystem;

    private string $root;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(self::ROOT);
        $this->filesystem->mkdir([self::ROOT.'/project', self::ROOT.'/outside', self::ROOT.'/home/.ssh', self::ROOT.'/home/.config/airlock', self::ROOT.'/airlock']);
        $this->filesystem->dumpFile(self::ROOT.'/home/.ssh/id_ed25519', 'PRIVATE-KEY-VALUE');
        $this->filesystem->dumpFile(self::ROOT.'/home/.config/airlock/grants.yaml', 'GRANTS-VALUE');
        $this->filesystem->dumpFile(self::ROOT.'/airlock/.env.local', 'AIRLOCK-SECRET-VALUE');
        $this->root = (string) realpath(self::ROOT);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove([self::ROOT, '/tmp/airlock-sandbox-test']);
    }

    public function testBubblewrapIsFoundAndWorksOnThisMachine(): void
    {
        $sandbox = $this->detector(SandboxMode::Auto)->detect();

        self::assertInstanceOf(Bubblewrap::class, $sandbox, $sandbox->describe());
    }

    public function testACommandWritesInTheProject(): void
    {
        $result = $this->runInSandbox('echo inside > made.txt && cat made.txt');

        self::assertSame("inside\n", $result->output);
        self::assertFileExists($this->root.'/project/made.txt');
    }

    public function testACommandWritesInTmp(): void
    {
        $result = $this->runInSandbox('echo t > /tmp/airlock-sandbox-test && echo ok');

        self::assertSame("ok\n", $result->output);
        self::assertFileExists('/tmp/airlock-sandbox-test');
    }

    public function testAWriteOutsideTheProjectFails(): void
    {
        $result = $this->runInSandbox(\sprintf('echo escaped > %s/outside/escaped.txt', $this->root));

        self::assertFalse($result->isSuccessful());
        self::assertStringContainsString('Read-only file system', $result->output);
        self::assertFileDoesNotExist($this->root.'/outside/escaped.txt');
    }

    public function testAWriteToTheHomeFails(): void
    {
        $result = $this->runInSandbox(\sprintf('echo pwned >> %s/home/.bashrc', $this->root));

        self::assertFalse($result->isSuccessful());
        self::assertFileDoesNotExist($this->root.'/home/.bashrc');
    }

    public function testADirectoryBesideTheProjectCannotBeMade(): void
    {
        $this->runInSandbox(\sprintf('mkdir %s/escape', $this->root));

        self::assertDirectoryDoesNotExist($this->root.'/escape');
    }

    public function testTheUsersKeysCannotBeRead(): void
    {
        $result = $this->runInSandbox(\sprintf('cat %s/home/.ssh/id_ed25519; echo end', $this->root));

        self::assertStringNotContainsString('PRIVATE-KEY-VALUE', $result->output);
        self::assertStringContainsString('end', $result->output);
    }

    public function testAirlocksOwnFilesCannotBeRead(): void
    {
        $result = $this->runInSandbox(\sprintf('cat %1$s/home/.config/airlock/grants.yaml %1$s/airlock/.env.local; echo end', $this->root));

        self::assertStringNotContainsString('GRANTS-VALUE', $result->output);
        self::assertStringNotContainsString('AIRLOCK-SECRET-VALUE', $result->output);
    }

    public function testAirlocksOwnFilesCannotBeChanged(): void
    {
        $this->runInSandbox(\sprintf('echo "rm -rf *" > %s/home/.config/airlock/grants.yaml', $this->root));

        self::assertSame('GRANTS-VALUE', $this->filesystem->readFile($this->root.'/home/.config/airlock/grants.yaml'));
    }

    public function testStrictModeLeavesNoNetworkButLoopback(): void
    {
        $result = $this->runInSandbox("tail -n +3 /proc/net/dev | cut -d: -f1 | tr -d ' '", SandboxMode::Strict);

        self::assertSame("lo\n", $result->output);
    }

    public function testAutoModeKeepsTheNetwork(): void
    {
        $result = $this->runInSandbox("tail -n +3 /proc/net/dev | cut -d: -f1 | tr -d ' '");

        self::assertNotSame("lo\n", $result->output);
    }

    public function testACommandDoesNotSeeAirlocksOwnEnvironment(): void
    {
        $secret = $_ENV['APP_SECRET'] ?? null;
        self::assertIsString($secret, 'The test environment sets APP_SECRET in .env.test.');

        $result = $this->runInSandbox('env');

        self::assertStringNotContainsString($secret, $result->output, 'APP_SECRET comes from Airlock\'s .env files.');
        self::assertStringNotContainsString('SYMFONY_DOTENV_VARS', $result->output);
    }

    public function testACommandPastItsTimeLimitIsStopped(): void
    {
        $result = $this->runInSandbox('echo started; sleep 10', timeoutSeconds: 1);

        self::assertTrue($result->hasTimedOut);
        self::assertNull($result->exitCode);
        self::assertSame("started\n", $result->output);
    }

    public function testTheExitCodeIsKept(): void
    {
        self::assertSame(3, $this->runInSandbox('exit 3')->exitCode);
    }

    private function runInSandbox(string $commandLine, SandboxMode $mode = SandboxMode::Auto, int $timeoutSeconds = 30): CommandResult
    {
        $runner = new CommandRunner($this->detector($mode), new ProjectDirectory($this->root.'/project'), $timeoutSeconds);

        return $runner->run($commandLine);
    }

    private function detector(SandboxMode $mode): SandboxDetector
    {
        return new SandboxDetector(new SandboxPaths($this->root.'/home', $this->root.'/airlock'), $mode);
    }
}
