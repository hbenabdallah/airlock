<?php

declare(strict_types=1);

namespace App\Tests\Sandbox;

use App\Configuration\ConfigDirectory;
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
use Symfony\Component\Process\Process;

/**
 * Real commands in real bubblewrap. Everything lives under var/, which is neither the project, /tmp nor a cache:
 * a write there is a write outside what the sandbox opens.
 */
#[RequiresOperatingSystemFamily('Linux')]
final class BubblewrapTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../var/test-sandbox';

    /** In the host's /tmp, where agents put their sockets. */
    private const string HOST_TMP = '/tmp/airlock-sandbox-test';

    private Filesystem $filesystem;

    private string $root;

    private ?Process $sshAgent = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove([self::ROOT, self::HOST_TMP]);
        $this->filesystem->mkdir([
            self::ROOT.'/project/.git/hooks', self::ROOT.'/outside', self::ROOT.'/home/.ssh', self::ROOT.'/home/.config/airlock',
            self::ROOT.'/airlock', self::ROOT.'/run', self::HOST_TMP,
        ]);
        $this->filesystem->dumpFile(self::ROOT.'/project/.git/config', "[core]\n");
        $this->filesystem->dumpFile(self::ROOT.'/home/.ssh/id_ed25519', 'PRIVATE-KEY-VALUE');
        $this->filesystem->dumpFile(self::ROOT.'/home/.config/airlock/grants.yaml', 'GRANTS-VALUE');
        $this->filesystem->dumpFile(self::ROOT.'/airlock/.env.local', 'AIRLOCK-SECRET-VALUE');
        $this->filesystem->dumpFile(self::ROOT.'/run/keyring-control', 'RUNTIME-VALUE');
        $this->filesystem->dumpFile(self::HOST_TMP.'/host-file', 'HOST-TMP-VALUE');
        $this->root = (string) realpath(self::ROOT);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->sshAgent?->stop(0);
        $this->filesystem->remove([self::ROOT, self::HOST_TMP]);
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

    public function testACommandWritesInAPrivateTmpThatOutlivesItNowhere(): void
    {
        $result = $this->runInSandbox('echo t > /tmp/airlock-made && cat /tmp/airlock-made');

        self::assertSame("t\n", $result->output);
        self::assertFileDoesNotExist('/tmp/airlock-made');
    }

    public function testACommandDoesNotSeeTheHostsTmp(): void
    {
        $result = $this->runInSandbox(\sprintf('cat %s/host-file; echo end', self::HOST_TMP));

        self::assertStringNotContainsString('HOST-TMP-VALUE', $result->output);
    }

    public function testACommandDoesNotSeeTheRuntimeDirectory(): void
    {
        $result = $this->runInSandbox(\sprintf('cat %s/run/keyring-control; echo end', $this->root));

        self::assertStringNotContainsString('RUNTIME-VALUE', $result->output);
    }

    public function testAProjectInsideTmpStaysWritable(): void
    {
        $project = self::HOST_TMP.'/project';
        $this->filesystem->mkdir($project);

        $result = $this->runInSandbox('echo inside > made.txt && cat made.txt', project: $project);

        self::assertSame("inside\n", $result->output);
        self::assertFileExists($project.'/made.txt');
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

    public function testAGitHookCannotBePlanted(): void
    {
        $result = $this->runInSandbox('echo "rm -rf ~" > .git/hooks/pre-commit');

        self::assertFalse($result->isSuccessful());
        self::assertFileDoesNotExist($this->root.'/project/.git/hooks/pre-commit');
    }

    public function testTheGitConfigurationCannotBeChanged(): void
    {
        $this->runInSandbox('echo "[core] hooksPath = /tmp/hooks" >> .git/config');

        self::assertSame("[core]\n", $this->filesystem->readFile($this->root.'/project/.git/config'));
    }

    public function testTheRestOfTheGitDirectoryStaysWritable(): void
    {
        $this->runInSandbox('touch .git/index');

        self::assertFileExists($this->root.'/project/.git/index');
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

    public function testTheSshAgentIsOutOfReachByDefault(): void
    {
        $socket = $this->startSshAgent();

        $result = $this->runInSandbox(\sprintf('echo "[$SSH_AUTH_SOCK]"; SSH_AUTH_SOCK=%s ssh-add -l; echo "exit $?"', $socket), sshAgentSocket: $socket);

        self::assertStringStartsWith("[]\n", $result->output, 'The variable naming the agent is unset.');
        self::assertStringEndsWith("exit 2\n", $result->output, 'ssh-add exits with 2 when it cannot reach an agent.');
    }

    public function testTheSshAgentCanBeLentToOneCommand(): void
    {
        $socket = $this->startSshAgent();

        $result = $this->runner(SandboxMode::Auto, 30, null, $socket)->runWithSshAgent('ssh-add -l; echo "exit $?"');

        self::assertStringEndsWith("exit 1\n", $result->output, 'ssh-add exits with 1 when it reaches an agent that holds no key.');
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

    private function startSshAgent(): string
    {
        $socket = self::HOST_TMP.'/agent.sock';
        $this->sshAgent = new Process(['ssh-agent', '-D', '-a', $socket]);
        $this->sshAgent->start();
        for ($attempt = 0; $attempt < 100 && !file_exists($socket); ++$attempt) {
            usleep(20_000);
        }
        self::assertFileExists($socket, 'ssh-agent did not start: '.$this->sshAgent->getErrorOutput());

        return $socket;
    }

    private function runInSandbox(string $commandLine, SandboxMode $mode = SandboxMode::Auto, int $timeoutSeconds = 30, ?string $project = null, ?string $sshAgentSocket = null): CommandResult
    {
        return $this->runner($mode, $timeoutSeconds, $project, $sshAgentSocket)->run($commandLine);
    }

    private function runner(SandboxMode $mode, int $timeoutSeconds, ?string $project, ?string $sshAgentSocket): CommandRunner
    {
        return new CommandRunner($this->detector($mode), new ProjectDirectory($project ?? $this->root.'/project'), $timeoutSeconds, $sshAgentSocket);
    }

    private function detector(SandboxMode $mode): SandboxDetector
    {
        $home = $this->root.'/home';
        $paths = new SandboxPaths($home, $this->root.'/airlock', new ConfigDirectory($home), $this->root.'/run');

        return new SandboxDetector($paths, $mode);
    }
}
