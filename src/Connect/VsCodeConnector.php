<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * VS Code: the server in the project's `.vscode/mcp.json`, run in the workspace folder. No file turns off its own
 * terminal tool.
 */
final readonly class VsCodeConnector implements ClientConnector
{
    #[\Override]
    public function client(): Client
    {
        return Client::VsCode;
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
            'type' => 'stdio',
            'command' => $context->server->command,
            'args' => $context->server->arguments,
            'env' => new ConfigTable(['AIRLOCK_PROJECT_DIR' => '${workspaceFolder}']),
        ]);

        return [new FileEdit($context->projectDirectory.'/.vscode/mcp.json', ConfigFormat::Json, [new SetEntry(ConfigPath::of('servers', 'airlock'), $server)])];
    }

    #[\Override]
    public function notes(Scope $scope): array
    {
        return [
            'VS Code\'s own terminal tool is still on: turn it off in the chat\'s tool picker, or the agent can run commands without Airlock.',
            '.vscode/mcp.json holds this machine\'s path to Airlock: keep it out of version control.',
        ];
    }
}
