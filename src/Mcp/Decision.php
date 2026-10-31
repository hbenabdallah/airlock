<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Policy\Verdict;

/**
 * What the user can answer when asked about a command.
 */
enum Decision: string
{
    case Once = 'once';
    case Session = 'session';
    case Always = 'always';
    case Refuse = 'refuse';

    /**
     * The answers offered for a line: one asked about every time can only be allowed once.
     *
     * @return non-empty-list<self>
     */
    public static function offeredFor(Verdict $verdict): array
    {
        return Verdict::AskEveryTime === $verdict ? [self::Once, self::Refuse] : self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Allow once',
            self::Session => 'Allow for this session',
            self::Always => 'Always allow in this project',
            self::Refuse => 'Refuse',
        };
    }
}
