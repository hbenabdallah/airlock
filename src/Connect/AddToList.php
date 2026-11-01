<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * One item in a list the user may share with other tools, such as a client's denied tools: added once, and taken back
 * only when the original did not have it.
 */
final readonly class AddToList implements ConfigChange
{
    public function __construct(
        private ConfigPath $path,
        public string $item,
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
        $parent = $this->path->parentOrCreate($root);
        $key = $this->path->key();
        $items = property_exists($parent, $key) ? $this->items($parent->{$key}) : [];
        if (!\in_array($this->item, $items, true)) {
            $items[] = $this->item;
        }
        $parent->{$key} = $items;
    }

    #[\Override]
    public function disconnect(\stdClass $root, ?\stdClass $original): void
    {
        $parent = $this->path->existingParent($root);
        $key = $this->path->key();
        if (!$original instanceof \stdClass || !$parent instanceof \stdClass || !property_exists($parent, $key) || $this->wasThere($original)) {
            return;
        }

        $parent->{$key} = array_values(array_filter($this->items($parent->{$key}), fn (mixed $item): bool => $item !== $this->item));
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
            Direction::Connect => \sprintf('adds "%s" to %s', $this->item, $this->path->dotted()),
            Direction::Disconnect => \sprintf('removes "%s" from %s, unless it was there before', $this->item, $this->path->dotted()),
        };
    }

    private function wasThere(\stdClass $original): bool
    {
        $parent = $this->path->existingParent($original);
        $key = $this->path->key();

        return $parent instanceof \stdClass && property_exists($parent, $key) && \in_array($this->item, $this->items($parent->{$key}), true);
    }

    /**
     * @return list<mixed>
     */
    private function items(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new UnsupportedConfigException(\sprintf('%s is not a list', $this->path->dotted()));
        }

        return $value;
    }
}
