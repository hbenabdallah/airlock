<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * One configuration file as it is and as it will be once Airlock has written it.
 */
final readonly class PlannedFile
{
    /**
     * @param list<string> $changes what Airlock changes in it, in words
     */
    public function __construct(
        public string $path,
        public bool $exists,
        public string $before,
        public string $after,
        public array $changes,
    ) {
    }

    public function isChanged(): bool
    {
        return $this->before !== $this->after;
    }
}
