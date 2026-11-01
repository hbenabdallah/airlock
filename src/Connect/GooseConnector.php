<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * Goose: Airlock as a stdio extension in `~/.config/goose/config.yaml`, and the developer extension, where it is
 * configured, kept to its file tools so that its shell tool is gone.
 */
final readonly class GooseConnector implements ClientConnector
{
    /** The developer extension's tools, its shell tool left out. */
    private const array DEVELOPER_TOOLS = ['write', 'edit', 'tree'];

    #[\Override]
    public function client(): Client
    {
        return Client::Goose;
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::User];
    }

    #[\Override]
    public function edits(Scope $scope, ConnectionContext $context): array
    {
        $extension = new ConfigTable([
            'name' => 'airlock',
            'type' => 'stdio',
            'enabled' => true,
            'cmd' => $context->server->command,
            'args' => $context->server->arguments,
            'timeout' => 300,
        ]);

        return [new FileEdit($context->home.'/.config/goose/config.yaml', ConfigFormat::Yaml, [
            new SetEntry(ConfigPath::of('extensions', 'airlock'), $extension),
            new SetInExisting(ConfigPath::of('extensions', 'developer', 'available_tools'), self::DEVELOPER_TOOLS),
        ])];
    }

    #[\Override]
    public function notes(Scope $scope): array
    {
        return ['Airlock writes config.yaml back without its comments; the file as it was before Airlock first changed it is kept in Airlock\'s directory, under originals/.'];
    }
}
