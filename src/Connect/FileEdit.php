<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * The changes Airlock makes to one of a client's configuration files.
 */
final readonly class FileEdit
{
    /**
     * @param non-empty-list<ConfigChange> $changes
     */
    public function __construct(
        public string $path,
        public ConfigFormat $format,
        public array $changes,
    ) {
    }
}
