<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A setting in a table the user shares with Airlock, such as a client's feature switch: set when connecting, put back
 * as it was when disconnecting.
 */
final readonly class SetShared implements ConfigChange
{
    public function __construct(
        private ConfigPath $path,
        public bool|int|string $value,
    ) {
    }

    #[\Override]
    public function path(): ConfigPath
    {
        return $this->path;
    }

    #[\Override]
    public function connect(\stdClass $root): void
    {
        $this->path->parentOrCreate($root)->{$this->path->key()} = $this->value;
    }

    #[\Override]
    public function disconnect(\stdClass $root, ?\stdClass $original): void
    {
        SharedValue::restore($this->path, $root, $original);
    }

    #[\Override]
    public function isShared(): bool
    {
        return true;
    }

    #[\Override]
    public function describe(Direction $direction): string
    {
        return match ($direction) {
            Direction::Connect => \sprintf('sets %s', $this->path->dotted()),
            Direction::Disconnect => \sprintf('puts %s back as it was', $this->path->dotted()),
        };
    }
}
