<?php

declare(strict_types=1);

namespace App\Tests\Connect;

use App\Connect\ConfigPath;
use App\Connect\ConfigTable;
use App\Connect\Direction;
use App\Connect\SetEntry;
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
        self::assertSame(self::CONNECTED, new TomlConfig()->edit('', $this->changes(), Direction::Connect));
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

            TOML, new TomlConfig()->edit($existing, $this->changes(), Direction::Connect));
    }

    public function testAnEnabledShellToolIsTurnedOffInPlace(): void
    {
        $content = new TomlConfig()->edit("[features]\nshell_tool = true\nweb_search = true\n", $this->changes(), Direction::Connect);

        self::assertStringContainsString("[features]\nshell_tool = false\nweb_search = true\n", $content);
    }

    public function testAnOlderAirlockTableIsReplacedWithItsSubtables(): void
    {
        $existing = "[mcp_servers.airlock]\ncommand = \"old\"\n\n[mcp_servers.airlock.env]\nX = \"1\"\n\n[features]\nshell_tool = false\n";

        self::assertSame(self::CONNECTED, new TomlConfig()->edit($existing, $this->changes(), Direction::Connect));
    }

    public function testConnectingTwiceChangesNothingTheSecondTime(): void
    {
        self::assertSame(self::CONNECTED, new TomlConfig()->edit(self::CONNECTED, $this->changes(), Direction::Connect));
    }

    public function testDisconnectingRemovesTheTableAndTheKey(): void
    {
        self::assertSame("[features]\n", new TomlConfig()->edit(self::CONNECTED, $this->changes(), Direction::Disconnect));
    }

    public function testAHeaderInsideAMultiLineStringIsText(): void
    {
        $existing = "instructions = \"\"\"\n[features]\n\"\"\"\n";

        $content = new TomlConfig()->edit($existing, $this->changes(), Direction::Connect);

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

        new TomlConfig()->edit($existing, $this->changes(), Direction::Connect);
    }

    /**
     * @return non-empty-list<SetEntry>
     */
    private function changes(): array
    {
        return [
            new SetEntry(ConfigPath::of('mcp_servers', 'airlock'), new ConfigTable(['command' => '/bin/airlock', 'args' => ['mcp:server']])),
            new SetEntry(ConfigPath::of('features', 'shell_tool'), false),
        ];
    }
}
