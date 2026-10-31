<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A setting on something the user configured, such as a client's own extension: left alone when that is not there.
 */
final readonly class SetInExisting implements ConfigChange
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
        $parent = $this->path->existingParent($root);
        if ($parent instanceof \stdClass) {
            $parent->{$this->path->key()} = ConfigNode::of($this->value);
        }
    }

    #[\Override]
    public function disconnect(\stdClass $root): void
    {
        $parent = $this->path->existingParent($root);
        if ($parent instanceof \stdClass) {
            unset($parent->{$this->path->key()});
        }
    }

    #[\Override]
    public function describe(Direction $direction): string
    {
        return match ($direction) {
            Direction::Connect => \sprintf('sets %s, where it is configured', $this->path->dotted()),
            Direction::Disconnect => \sprintf('removes %s', $this->path->dotted()),
        };
    }
}
