<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * The edit shared by every file read as a tree of tables, JSON and YAML.
 */
final class ConfigTree
{
    /**
     * @param non-empty-list<ConfigChange> $changes
     */
    public static function edit(\stdClass $root, array $changes, Direction $direction): void
    {
        foreach ($changes as $change) {
            match ($direction) {
                Direction::Connect => $change->connect($root),
                Direction::Disconnect => $change->disconnect($root),
            };
        }
    }
}
