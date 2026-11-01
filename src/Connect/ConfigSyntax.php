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
    public function connect(string $content, array $changes): string;

    /**
     * @param non-empty-list<ConfigChange> $changes
     * @param string|null                  $original the file as it was before Airlock first changed it, '' when it did
     *                                               not exist, null when unknown
     *
     * @throws UnsupportedConfigException when the file cannot be edited without risking what else it holds
     */
    public function disconnect(string $content, array $changes, ?string $original): string;
}
