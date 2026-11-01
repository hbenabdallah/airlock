<?php

declare(strict_types=1);

namespace App\Tests\Connect;

use App\Connect\ConfigPath;
use App\Connect\ConfigTable;
use App\Connect\SetEntry;
use App\Connect\SetShared;
use App\Connect\TomlConfig;
use App\Connect\UnsupportedConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TomlConfigTest extends TestCase
{
    private const string CONNECTED = <<<'TOML'
        [mcp_servers.airlock]
        command = "/bin/airlock"
        args = ["mcp:server"]

        [features]
        shell_tool = false

        TOML;

    public function testAMissingFileGetsTheServerTableAndTheFeature(): void
    {
        self::assertSame(self::CONNECTED, new TomlConfig()->connect('', $this->changes()));
    }

    public function testCommentsAndOtherTablesAreKeptAsTheyWere(): void
    {
        $existing = <<<'TOML'
            # my settings
            model = "a-model"   # the default

            [features]
            # keep this
            web_search = true

            [mcp_servers.other]
            command = "other"
            TOML;

        self::assertSame(<<<'TOML'
            # my settings
            model = "a-model"   # the default

            [features]
            shell_tool = false
            # keep this
            web_search = true

            [mcp_servers.other]
            command = "other"

            [mcp_servers.airlock]
            command = "/bin/airlock"
            args = ["mcp:server"]

            TOML, new TomlConfig()->connect($existing, $this->changes()));
    }

    public function testAnEnabledShellToolIsTurnedOffInPlace(): void
    {
        $content = new TomlConfig()->connect("[features]\nshell_tool = true\nweb_search = true\n", $this->changes());

        self::assertStringContainsString("[features]\nshell_tool = false\nweb_search = true\n", $content);
    }

    public function testAnOlderAirlockTableIsReplacedWithItsSubtables(): void
    {
        $existing = "[mcp_servers.airlock]\ncommand = \"old\"\n\n[mcp_servers.airlock.env]\nX = \"1\"\n\n[features]\nshell_tool = false\n";

        self::assertSame(self::CONNECTED, new TomlConfig()->connect($existing, $this->changes()));
    }

    public function testConnectingTwiceChangesNothingTheSecondTime(): void
    {
        self::assertSame(self::CONNECTED, new TomlConfig()->connect(self::CONNECTED, $this->changes()));
    }

    public function testDisconnectingRemovesTheTableAndTheKey(): void
    {
        self::assertSame("[features]\n", new TomlConfig()->disconnect(self::CONNECTED, $this->changes(), ''));
    }

    public function testAShellToolTheUserHadTurnedOffStaysOff(): void
    {
        $original = "[features]\nshell_tool = false # mine\n";
        $connected = new TomlConfig()->connect($original, $this->changes());

        self::assertStringEndsWith("[features]\nshell_tool = false # mine\n", new TomlConfig()->disconnect($connected, $this->changes(), $original));
    }

    public function testWithoutTheOriginalTheFeatureIsLeftAsItIs(): void
    {
        self::assertSame("[features]\nshell_tool = false\n", new TomlConfig()->disconnect(self::CONNECTED, $this->changes(), null));
    }

    public function testTheCommentAboveTheNextTableSurvivesReplacingAndRemovingAirlocks(): void
    {
        $existing = "[mcp_servers.airlock]\ncommand = \"old\"\n\n# GitHub server\n[mcp_servers.github]\ncommand = \"gh\"\n";

        $connected = new TomlConfig()->connect($existing, $this->changes());
        $disconnected = new TomlConfig()->disconnect($connected, $this->changes(), '');

        self::assertStringContainsString("args = [\"mcp:server\"]\n\n# GitHub server\n[mcp_servers.github]\n", $connected);
        self::assertSame($connected, new TomlConfig()->connect($connected, $this->changes()), 'Connecting again keeps the comment where it is.');
        self::assertStringStartsWith("# GitHub server\n[mcp_servers.github]\ncommand = \"gh\"\n", $disconnected);
    }

    public function testAHeaderInsideAMultiLineStringIsText(): void
    {
        $existing = "instructions = \"\"\"\n[features]\n\"\"\"\n";

        $content = new TomlConfig()->connect($existing, $this->changes());

        self::assertStringStartsWith($existing."\n[mcp_servers.airlock]", $content);
        self::assertStringEndsWith("[features]\nshell_tool = false\n", $content);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tablesWrittenOtherwise(): iterable
    {
        yield 'a dotted key at the root' => ["features.shell_tool = true\n"];
        yield 'an inline table at the root' => ["mcp_servers = { airlock = { command = \"x\" } }\n"];
        yield 'an inline table in its parent' => ["[mcp_servers]\nairlock = { command = \"x\" }\n"];
    }

    #[DataProvider('tablesWrittenOtherwise')]
    public function testATableWrittenAsKeysIsLeftToTheUser(string $existing): void
    {
        $this->expectException(UnsupportedConfigException::class);

        new TomlConfig()->connect($existing, $this->changes());
    }

    /**
     * @return non-empty-list<SetEntry|SetShared>
     */
    private function changes(): array
    {
        return [
            new SetEntry(ConfigPath::of('mcp_servers', 'airlock'), new ConfigTable(['command' => '/bin/airlock', 'args' => ['mcp:server']])),
            new SetShared(ConfigPath::of('features', 'shell_tool'), false),
        ];
    }
}
