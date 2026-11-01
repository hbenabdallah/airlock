<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * The MCP clients `airlock connect` knows how to configure: each supports elicitation, without which Airlock cannot
 * ask before a command runs.
 */
enum Client: string
{
    case ClaudeCode = 'claude-code';
    case Codex = 'codex';
    case Cursor = 'cursor';
    case VsCode = 'vscode';
    case Goose = 'goose';

    public function label(): string
    {
        return match ($this) {
            self::ClaudeCode => 'Claude Code',
            self::Codex => 'Codex',
            self::Cursor => 'Cursor',
            self::VsCode => 'VS Code',
            self::Goose => 'Goose',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $client): string => $client->value, self::cases());
    }
}
