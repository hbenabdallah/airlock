<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * One table of a TOML file, header line first; the root table has no header and the name ''.
 */
final class TomlSection
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public readonly string $name,
        private array $lines,
    ) {
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }

    public function isEmpty(): bool
    {
        return [] === $this->lines || [''] === $this->lines;
    }

    public function endsWithBlankLine(): bool
    {
        return [] === $this->lines || '' === $this->lines[\count($this->lines) - 1];
    }

    public function clear(): void
    {
        $this->lines = [];
    }

    /**
     * Sets the key's line, in place when the table already has one, else first after the header.
     */
    public function setKey(string $key, string $line): void
    {
        foreach ($this->lines as $number => $existing) {
            if ($number > 0 && $this->definesKey($existing, $key)) {
                $this->lines[$number] = $line;

                return;
            }
        }
        array_splice($this->lines, 1, 0, [$line]);
    }

    public function removeKey(string $key): void
    {
        $kept = [];
        foreach ($this->lines as $number => $line) {
            if (0 === $number || !$this->definesKey($line, $key)) {
                $kept[] = $line;
            }
        }
        $this->lines = $kept;
    }

    public function keyLine(string $key): ?string
    {
        foreach ($this->lines as $number => $line) {
            if ($number > 0 && $this->definesKey($line, $key)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The blank and comment lines at its end: they sit above the next table, and belong with it.
     *
     * @return list<string>
     */
    public function trailingLines(): array
    {
        $trailing = [];
        for ($number = \count($this->lines) - 1; $number > 0; --$number) {
            if (1 !== preg_match('/^\s*(#.*)?$/', $this->lines[$number])) {
                break;
            }
            array_unshift($trailing, $this->lines[$number]);
        }

        return $trailing;
    }

    /**
     * @param list<string> $lines
     */
    public function addAll(array $lines): void
    {
        array_push($this->lines, ...$lines);
    }

    public function definesKey(string $line, string $key): bool
    {
        return 1 === preg_match('/^\s*["\']?'.preg_quote($key, '/').'["\']?\s*[.=]/', $line);
    }
}
