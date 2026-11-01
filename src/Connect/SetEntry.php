<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * An entry that is Airlock's own, such as its server: set whole when connecting, removed when disconnecting.
 */
final readonly class SetEntry implements ConfigChange
{
    /**
     * @param bool|int|string|list<string>|ConfigTable $value
     */
    public function __construct(
        private ConfigPath $path,
        public bool|int|string|array|ConfigTable $value,
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
        $this->path->parentOrCreate($root)->{$this->path->key()} = ConfigNode::of($this->value);
    }

    #[\Override]
    public function disconnect(\stdClass $root, ?\stdClass $original): void
    {
        $parent = $this->path->existingParent($root);
        if ($parent instanceof \stdClass) {
            unset($parent->{$this->path->key()});
        }
    }

    #[\Override]
    public function isShared(): bool
    {
        return false;
    }

    #[\Override]
    public function describe(Direction $direction): string
    {
        return match ($direction) {
            Direction::Connect => \sprintf('sets %s', $this->path->dotted()),
            Direction::Disconnect => \sprintf('removes %s', $this->path->dotted()),
        };
    }
}
