<?php

declare(strict_types=1);

namespace App\Tests\Connect;

use App\Connect\ConfigPath;
use App\Connect\ConfigTable;
use App\Connect\Direction;
use App\Connect\SetEntry;
use App\Connect\SetInExisting;
use App\Connect\UnsupportedConfigException;
use App\Connect\YamlConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigTest extends TestCase
{
    private const string WITH_DEVELOPER = <<<'YAML'
        GOOSE_MODE: approve
        extensions:
          developer:
            bundled: true
            enabled: true
            name: developer
            type: builtin
        YAML;

    public function testTheExtensionIsAddedAndTheExistingOneRestricted(): void
    {
        $content = new YamlConfig()->edit(self::WITH_DEVELOPER, $this->changes(), Direction::Connect);

        self::assertSame([
            'GOOSE_MODE' => 'approve',
            'extensions' => [
                'developer' => ['bundled' => true, 'enabled' => true, 'name' => 'developer', 'type' => 'builtin', 'available_tools' => ['write', 'edit']],
                'airlock' => ['name' => 'airlock', 'type' => 'stdio', 'cmd' => '/bin/airlock', 'args' => ['mcp:server']],
            ],
        ], Yaml::parse($content));
    }

    public function testAnExtensionTheUserDoesNotHaveIsNotCreated(): void
    {
        $content = new YamlConfig()->edit("GOOSE_MODE: approve\n", $this->changes(), Direction::Connect);

        self::assertSame([
            'GOOSE_MODE' => 'approve',
            'extensions' => ['airlock' => ['name' => 'airlock', 'type' => 'stdio', 'cmd' => '/bin/airlock', 'args' => ['mcp:server']]],
        ], Yaml::parse($content));
    }

    public function testDisconnectingRemovesTheExtensionAndTheRestriction(): void
    {
        $connected = new YamlConfig()->edit(self::WITH_DEVELOPER, $this->changes(), Direction::Connect);

        $content = new YamlConfig()->edit($connected, $this->changes(), Direction::Disconnect);

        self::assertSame(Yaml::parse(self::WITH_DEVELOPER), Yaml::parse($content));
    }

    public function testConnectingTwiceChangesNothingTheSecondTime(): void
    {
        $once = new YamlConfig()->edit(self::WITH_DEVELOPER, $this->changes(), Direction::Connect);

        self::assertSame($once, new YamlConfig()->edit($once, $this->changes(), Direction::Connect));
    }

    public function testInvalidYamlIsRefused(): void
    {
        $this->expectException(UnsupportedConfigException::class);

        new YamlConfig()->edit("extensions: [\n", $this->changes(), Direction::Connect);
    }

    /**
     * @return non-empty-list<SetEntry|SetInExisting>
     */
    private function changes(): array
    {
        return [
            new SetEntry(ConfigPath::of('extensions', 'airlock'), new ConfigTable(['name' => 'airlock', 'type' => 'stdio', 'cmd' => '/bin/airlock', 'args' => ['mcp:server']])),
            new SetInExisting(ConfigPath::of('extensions', 'developer', 'available_tools'), ['write', 'edit']),
        ];
    }
}
