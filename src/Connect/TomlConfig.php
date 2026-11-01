<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A TOML file, edited in place: a whole table for Airlock's own entry, one key for a setting in a shared table, put
 * back from the original when disconnecting.
 */
final readonly class TomlConfig implements ConfigSyntax
{
    #[\Override]
    public function connect(string $content, array $changes): string
    {
        $document = TomlDocument::parse($content);
        foreach ($changes as $change) {
            if ($change instanceof SetEntry && $change->value instanceof ConfigTable) {
                $name = $this->tableName($change);
                $document->assertTablesOnly($name);
                $document->replaceTable($name, $this->table($name, $change->value));
                continue;
            }
            [$table, $key] = $this->keyIn($change);
            $document->assertTablesOnly($table);
            $document->setKey($table, $key, $this->name($key).' = '.$this->value($change->value));
        }

        return $document->render();
    }

    #[\Override]
    public function disconnect(string $content, array $changes, ?string $original): string
    {
        $document = TomlDocument::parse($content);
        $originalDocument = null === $original ? null : TomlDocument::parse($original);
        foreach ($changes as $change) {
            if ($change instanceof SetEntry && $change->value instanceof ConfigTable) {
                $document->removeTable($this->tableName($change));
                continue;
            }
            [$table, $key] = $this->keyIn($change);
            if (!$originalDocument instanceof TomlDocument) {
                continue;
            }
            $originalLine = $originalDocument->keyLine($table, $key);
            if (null === $originalLine) {
                $document->removeKey($table, $key);
            } else {
                $document->setKey($table, $key, $originalLine);
            }
        }

        return $document->render();
    }

    private function tableName(SetEntry $change): string
    {
        $keys = $change->path()->keys;
        if (2 !== \count($keys)) {
            throw new \LogicException(\sprintf('A TOML table is named by two keys, not %s.', $change->path()->dotted()));
        }

        return $this->name($keys[0]).'.'.$this->name($keys[1]);
    }

    /**
     * @phpstan-assert SetShared $change
     *
     * @return array{string, string}
     */
    private function keyIn(ConfigChange $change): array
    {
        $keys = $change->path()->keys;
        if (!$change instanceof SetShared || 2 !== \count($keys)) {
            throw new \LogicException(\sprintf('TOML files take a table of Airlock\'s or a shared key in a table, not %s.', $change->path()->dotted()));
        }

        return [$keys[0], $keys[1]];
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
