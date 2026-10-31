<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * What the user can answer when asked about a command.
 */
enum Decision: string
{
    case Once = 'once';
    case Refuse = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Allow once',
            self::Refuse => 'Refuse',
        };
    }
}
