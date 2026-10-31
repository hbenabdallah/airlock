<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Connect\Client;
use App\Connect\ClientConfigFiles;
use App\Connect\ClientConnectors;
use App\Tests\Output\RecordedResponses;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class ConnectCommandTest extends KernelTestCase
{
    private const string ROOT = __DIR__.'/../../var/test-connect';

    private const string HOME = self::ROOT.'/home';

    private const string PROJECT = self::ROOT.'/project';

    private Filesystem $filesystem;

    /** @var array<mixed> */
    private array $server;

    /** @var array<mixed> */
    private array $environment;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(self::ROOT);
        $this->filesystem->mkdir([self::HOME, self::PROJECT]);
        $this->server = $_SERVER;
        $this->environment = $_ENV;
        $_SERVER['HOME'] = $_ENV['HOME'] = self::HOME;
        $_SERVER['AIRLOCK_PROJECT_DIR'] = $_ENV['AIRLOCK_PROJECT_DIR'] = self::PROJECT;
        unset($_SERVER['CODEX_HOME'], $_ENV['CODEX_HOME']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->environment;
        $this->filesystem->remove(self::ROOT);
        parent::tearDown();
    }

    public function testClaudeCodeGetsTheServerAndLosesItsShellTools(): void
    {
        $tester = $this->tester();

        $status = $tester->execute(['client' => 'claude-code']);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        $servers = $this->json(self::PROJECT.'/.mcp.json');
        self::assertSame('stdio', RecordedResponses::at($servers, 'mcpServers', 'airlock', 'type'));
        self::assertSame('mcp:server', RecordedResponses::at($servers, 'mcpServers', 'airlock', 'args', 1));
        self::assertSame(['deny' => ['Bash', 'Monitor'], 'allow' => ['mcp__airlock__*']], $this->json(self::PROJECT.'/.claude/settings.local.json')['permissions'] ?? null);
    }

    public function testCheckSaysWhetherAirlockIsConnected(): void
    {
        $before = $this->tester();
        self::assertSame(Command::FAILURE, $before->execute(['client' => 'claude-code', '--check' => true]));
        self::assertFileDoesNotExist(self::PROJECT.'/.mcp.json', '--check writes nothing.');

        $this->tester()->execute(['client' => 'claude-code']);

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['client' => 'claude-code', '--check' => true]));
    }

    public function testRemovingTakesBackOnlyWhatConnectingAdded(): void
    {
        $this->filesystem->dumpFile(self::PROJECT.'/.claude/settings.local.json', '{"permissions": {"deny": ["WebFetch"]}}');
        $this->tester()->execute(['client' => 'claude-code']);

        $status = $this->tester()->execute(['client' => 'claude-code', '--remove' => true]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(['deny' => ['WebFetch'], 'allow' => []], $this->json(self::PROJECT.'/.claude/settings.local.json')['permissions'] ?? null);
        self::assertSame(['mcpServers' => []], $this->json(self::PROJECT.'/.mcp.json'));
    }

    public function testADryRunWritesNothing(): void
    {
        $tester = $this->tester();

        $tester->execute(['client' => 'cursor', '--dry-run' => true]);

        self::assertFileDoesNotExist(self::PROJECT.'/.cursor/mcp.json');
        self::assertStringContainsString('sets mcpServers.airlock', $tester->getDisplay());
    }

    public function testTheFirstChangeKeepsTheFileAsItWasWithItsPermissions(): void
    {
        $original = '{"oauthAccount": {"emailAddress": "you@example.test"}}';
        $this->filesystem->dumpFile(self::HOME.'/.claude.json', $original);
        $this->filesystem->chmod(self::HOME.'/.claude.json', 0o600);

        $this->tester()->execute(['client' => 'claude-code', '--scope' => 'user']);

        $backup = self::HOME.'/.claude.json'.ClientConfigFiles::BACKUP_SUFFIX;
        self::assertStringEqualsFile($backup, $original);
        self::assertSame(0o600, fileperms($backup) & 0o777, 'The copy of a file holding credentials is no more readable than the file.');
        self::assertSame(0o600, fileperms(self::HOME.'/.claude.json') & 0o777);
        self::assertNotNull(RecordedResponses::at($this->json(self::HOME.'/.claude.json'), 'mcpServers', 'airlock'));
    }

    public function testCodexIsConfiguredWhereCodexHomeSays(): void
    {
        $_SERVER['CODEX_HOME'] = $_ENV['CODEX_HOME'] = self::ROOT.'/codex';

        $this->tester()->execute(['client' => 'codex']);

        $config = (string) file_get_contents(self::ROOT.'/codex/config.toml');
        self::assertStringContainsString("[mcp_servers.airlock]\n", $config);
        self::assertStringContainsString("[features]\nshell_tool = false\n", $config);
    }

    public function testGooseKeepsItsDeveloperExtensionToTheFileTools(): void
    {
        $this->filesystem->dumpFile(self::HOME.'/.config/goose/config.yaml', "extensions:\n  developer:\n    enabled: true\n    name: developer\n    type: builtin\n");

        $this->tester()->execute(['client' => 'goose']);

        $config = Yaml::parseFile(self::HOME.'/.config/goose/config.yaml');
        self::assertSame(['enabled' => true, 'name' => 'developer', 'type' => 'builtin', 'available_tools' => ['write', 'edit', 'tree']], RecordedResponses::at($config, 'extensions', 'developer'));
        self::assertSame('stdio', RecordedResponses::at($config, 'extensions', 'airlock', 'type'));
    }

    public function testAFileAirlockCannotEditSafelyIsLeftAsItWas(): void
    {
        $original = "{\n  // my servers\n  \"servers\": {}\n}\n";
        $this->filesystem->dumpFile(self::PROJECT.'/.vscode/mcp.json', $original);
        $tester = $this->tester();

        $status = $tester->execute(['client' => 'vscode']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringEqualsFile(self::PROJECT.'/.vscode/mcp.json', $original);
        self::assertStringContainsString('Make the change by hand', $tester->getDisplay());
    }

    public function testAClientAirlockDoesNotKnowIsRefused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['client' => 'another-editor']));
        self::assertStringContainsString('does not know the client "another-editor"', $tester->getDisplay());
    }

    public function testAScopeTheClientDoesNotOfferIsRefused(): void
    {
        self::assertSame(Command::FAILURE, $this->tester()->execute(['client' => 'codex', '--scope' => 'project']));
    }

    public function testWithoutAClientTheEntryForAnyOtherIsShown(): void
    {
        $tester = $this->tester();

        $tester->execute([]);

        self::assertStringContainsString('"mcp:server"', $tester->getDisplay());
        self::assertStringContainsString('needs MCP elicitation', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{Client}>
     */
    public static function clients(): iterable
    {
        foreach (Client::cases() as $client) {
            yield $client->value => [$client];
        }
    }

    #[DataProvider('clients')]
    public function testEveryClientHasAConnector(Client $client): void
    {
        $connectors = self::getContainer()->get(ClientConnectors::class);

        self::assertSame($client, $connectors->for($client)->client());
    }

    private function tester(): CommandTester
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('connect'));
        $tester->setInputs(['yes']);

        return $tester;
    }

    /**
     * @return array<mixed>
     */
    private function json(string $path): array
    {
        $content = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }
}
