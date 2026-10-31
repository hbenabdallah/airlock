<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * Cursor: the server in the project's `.cursor/mcp.json`. No file turns off Cursor's own terminal tool.
 */
final readonly class CursorConnector implements ClientConnector
{
    #[\Override]
    public function client(): Client
    {
        return Client::Cursor;
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::Project];
    }

    #[\Override]
    public function edits(Scope $scope, ConnectionContext $context): array
    {
        $server = new ConfigTable([
            'command' => $context->server->command,
            'args' => $context->server->arguments,
            'env' => new ConfigTable(['AIRLOCK_PROJECT_DIR' => $context->projectDirectory]),
        ]);

        return [new FileEdit($context->projectDirectory.'/.cursor/mcp.json', ConfigFormat::Json, [new SetEntry(ConfigPath::of('mcpServers', 'airlock'), $server)])];
    }

    #[\Override]
    public function notes(Scope $scope): array
    {
        return [
            'Cursor\'s own terminal tool is still on: turn it off in Cursor\'s agent settings, or the agent can run commands without Airlock.',
            '.cursor/mcp.json holds this machine\'s paths: keep it out of version control.',
        ];
    }
}
