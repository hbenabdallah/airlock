<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * Claude Code: the server in `.mcp.json` (project) or `~/.claude.json` (user), and in the matching settings file its
 * Bash and Monitor tools denied, which removes them from the agent, and Airlock's tools allowed, since Airlock asks
 * itself.
 */
final readonly class ClaudeCodeConnector implements ClientConnector
{
    #[\Override]
    public function client(): Client
    {
        return Client::ClaudeCode;
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::Project, Scope::User];
    }

    #[\Override]
    public function edits(Scope $scope, ConnectionContext $context): array
    {
        [$servers, $settings] = match ($scope) {
            Scope::Project => [$context->projectDirectory.'/.mcp.json', $context->projectDirectory.'/.claude/settings.local.json'],
            Scope::User => [$context->home.'/.claude.json', $context->home.'/.claude/settings.json'],
        };
        $server = new ConfigTable(['type' => 'stdio', 'command' => $context->server->command, 'args' => $context->server->arguments]);

        return [
            new FileEdit($servers, ConfigFormat::Json, [new SetEntry(ConfigPath::of('mcpServers', 'airlock'), $server)]),
            new FileEdit($settings, ConfigFormat::Json, [
                new AddToList(ConfigPath::of('permissions', 'deny'), 'Bash'),
                new AddToList(ConfigPath::of('permissions', 'deny'), 'Monitor'),
                new AddToList(ConfigPath::of('permissions', 'allow'), 'mcp__airlock__*'),
            ]),
        ];
    }

    #[\Override]
    public function notes(Scope $scope): array
    {
        return match ($scope) {
            Scope::Project => ['.mcp.json holds this machine\'s path to Airlock: keep it out of version control. Claude Code asks once to approve the server it names.'],
            Scope::User => [],
        };
    }
}
