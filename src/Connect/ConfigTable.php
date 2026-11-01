<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A table of settings, kept apart from a list so that each file format writes it as a table.
 */
final readonly class ConfigTable
{
    /**
     * @param array<non-empty-string, bool|int|string|list<string>|ConfigTable> $entries
     */
    public function __construct(public array $entries)
    {
    }
}
