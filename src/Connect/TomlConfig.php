<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A TOML file, edited in place: a whole table for Airlock's own entry, one key for a setting in a shared table.
 */
final readonly class TomlConfig implements ConfigSyntax
{
    #[\Override]
    public function edit(string $content, array $changes, Direction $direction): string
    {
        $document = TomlDocument::parse($content);
        foreach ($changes as $change) {
            $this->apply($document, $change, $direction);
        }

        return $document->render();
    }

    private function apply(TomlDocument $document, ConfigChange $change, Direction $direction): void
    {
        $keys = $change->path()->keys;
        if (!$change instanceof SetEntry || 2 !== \count($keys)) {
            throw new \LogicException(\sprintf('TOML files take only a table or a key in a table, not %s.', $change->describe($direction)));
        }

        [$table, $key] = $keys;
        if ($change->value instanceof ConfigTable) {
            $name = $this->name($table).'.'.$this->name($key);
            $document->assertTablesOnly($name);
            match ($direction) {
                Direction::Connect => $document->replaceTable($name, $this->table($name, $change->value)),
                Direction::Disconnect => $document->removeTable($name),
            };

            return;
        }

        $document->assertTablesOnly($table);
        match ($direction) {
            Direction::Connect => $document->setKey($table, $key, $this->name($key).' = '.$this->value($change->value)),
            Direction::Disconnect => $document->removeKey($table, $key),
        };
    }

    /**
     * @return list<string>
     */
    private function table(string $name, ConfigTable $table): array
    {
        $lines = [\sprintf('[%s]', $name)];
        foreach ($table->entries as $key => $value) {
            $lines[] = $this->name($key).' = '.$this->value($value);
        }

        return $lines;
    }

    /**
     * @param bool|int|string|list<string>|ConfigTable $value
     */
    private function value(bool|int|string|array|ConfigTable $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_string($value)) {
            return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        }
        if (\is_array($value)) {
            return '['.implode(', ', array_map($this->value(...), $value)).']';
        }

        $pairs = [];
        foreach ($value->entries as $key => $entry) {
            $pairs[] = $this->name($key).' = '.$this->value($entry);
        }

        return '{ '.implode(', ', $pairs).' }';
    }

    private function name(string $key): string
    {
        return 1 === preg_match('/^[A-Za-z0-9_-]+$/', $key) ? $key : json_encode($key, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
