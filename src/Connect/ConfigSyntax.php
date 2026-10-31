<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * Edits one kind of configuration file and gives back its new content: the content unchanged, byte for byte, when
 * nothing changes.
 */
interface ConfigSyntax
{
    /**
     * @param non-empty-list<ConfigChange> $changes
     *
     * @throws UnsupportedConfigException when the file cannot be edited without risking what else it holds
     */
    public function edit(string $content, array $changes, Direction $direction): string;
}
