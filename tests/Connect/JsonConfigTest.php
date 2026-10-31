<?php

declare(strict_types=1);

namespace App\Tests\Connect;

use App\Connect\AddToList;
use App\Connect\ConfigPath;
use App\Connect\ConfigTable;
use App\Connect\Direction;
use App\Connect\JsonConfig;
use App\Connect\SetEntry;
use App\Connect\UnsupportedConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonConfigTest extends TestCase
{
    public function testAMissingFileGetsTheServerAndTheDeniedTool(): void
    {
        $content = new JsonConfig()->edit('', $this->changes(), Direction::Connect);

        self::assertSame(<<<'JSON'
            {
                "mcpServers": {
                    "airlock": {
                        "command": "/bin/airlock",
                        "args": [
                            "mcp:server"
                        ],
                        "env": {}
                    }
                },
                "permissions": {
                    "deny": [
                        "Bash"
                    ]
                }
            }

            JSON, $content);
    }

    public function testWhatElseTheFileHoldsIsKept(): void
    {
        $existing = '{"theme": "dark", "mcpServers": {"other": {"command": "x"}}, "permissions": {"deny": ["WebFetch"], "allow": []}}';

        $content = new JsonConfig()->edit($existing, $this->changes(), Direction::Connect);

        self::assertSame(
            ['theme' => 'dark', 'mcpServers' => ['other' => ['command' => 'x'], 'airlock' => ['command' => '/bin/airlock', 'args' => ['mcp:server'], 'env' => []]], 'permissions' => ['deny' => ['WebFetch', 'Bash'], 'allow' => []]],
            json_decode($content, true),
        );
    }

    public function testConnectingTwiceChangesNothingTheSecondTime(): void
    {
        $once = new JsonConfig()->edit('{"permissions": {"deny": ["Bash"]}}', $this->changes(), Direction::Connect);

        self::assertSame($once, new JsonConfig()->edit($once, $this->changes(), Direction::Connect));
    }

    public function testDisconnectingTakesBackOnlyWhatAirlockAdded(): void
    {
        $connected = new JsonConfig()->edit('{"theme": "dark", "permissions": {"deny": ["WebFetch"]}}', $this->changes(), Direction::Connect);

        $content = new JsonConfig()->edit($connected, $this->changes(), Direction::Disconnect);

        self::assertSame(['theme' => 'dark', 'permissions' => ['deny' => ['WebFetch']], 'mcpServers' => []], json_decode($content, true));
    }

    public function testAFileWithoutAirlockIsLeftByteForByteWhenDisconnecting(): void
    {
        $existing = "{\"theme\":   \"dark\"}\n";

        self::assertSame($existing, new JsonConfig()->edit($existing, $this->changes(), Direction::Disconnect));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function filesAirlockLeavesToTheUser(): iterable
    {
        yield 'comments' => ["{\n  // my servers\n  \"mcpServers\": {}\n}"];
        yield 'a trailing comma' => ['{"mcpServers": {},}'];
        yield 'a list' => ['["mcpServers"]'];
        yield 'a table that is not one' => ['{"mcpServers": "none"}'];
        yield 'a list that is not one' => ['{"permissions": {"deny": "Bash"}}'];
    }

    #[DataProvider('filesAirlockLeavesToTheUser')]
    public function testAFileAirlockCannotEditSafelyIsRefused(string $existing): void
    {
        $this->expectException(UnsupportedConfigException::class);

        new JsonConfig()->edit($existing, $this->changes(), Direction::Connect);
    }

    /**
     * @return non-empty-list<SetEntry|AddToList>
     */
    private function changes(): array
    {
        return [
            new SetEntry(ConfigPath::of('mcpServers', 'airlock'), new ConfigTable(['command' => '/bin/airlock', 'args' => ['mcp:server'], 'env' => new ConfigTable([])])),
            new AddToList(ConfigPath::of('permissions', 'deny'), 'Bash'),
        ];
    }
}
