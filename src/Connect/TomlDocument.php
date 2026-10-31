<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A TOML file as its tables, line for line: what Airlock does not touch is written back exactly as it was read,
 * comments included.
 */
final class TomlDocument
{
    private const string TABLE = '/^\s*\[(?!\[)\s*([^\[\]]+?)\s*\]\s*(#.*)?$/';

    private const string ARRAY_OF_TABLES = '/^\s*\[\[\s*([^\[\]]+?)\s*\]\]\s*(#.*)?$/';

    /**
     * @param non-empty-list<TomlSection> $sections the root first
     */
    private function __construct(private array $sections)
    {
    }

    public static function parse(string $content): self
    {
        $current = new TomlSection('', []);
        $sections = [$current];
        $openString = null;
        foreach (explode("\n", $content) as $line) {
            $header = null === $openString ? self::header($line) : null;
            if (null === $header) {
                $current->add($line);
            } else {
                $current = new TomlSection($header, [$line]);
                $sections[] = $current;
            }
            $openString = self::openStringAfter($line, $openString);
        }

        return new self($sections);
    }

    public function render(): string
    {
        $lines = [];
        foreach ($this->sections as $section) {
            array_push($lines, ...$section->lines());
        }

        return implode("\n", $lines);
    }

    /**
     * @throws UnsupportedConfigException when the table is written as dotted keys or an inline table
     */
    public function assertTablesOnly(string $table): void
    {
        foreach ($this->sections as $section) {
            $relative = $this->relativeName($table, $section->name);
            if (null === $relative) {
                continue;
            }
            foreach ($section->lines() as $line) {
                if ($section->definesKey($line, explode('.', $relative)[0])) {
                    throw new UnsupportedConfigException(\sprintf('it writes [%s] as dotted keys or an inline table', $table));
                }
            }
        }
    }

    /**
     * @param list<string> $lines the table's header and keys
     */
    public function replaceTable(string $table, array $lines): void
    {
        $replaced = false;
        $sections = [];
        foreach ($this->sections as $section) {
            if (!$this->isWithin($section->name, $table)) {
                $sections[] = $section;
                continue;
            }
            if (!$replaced) {
                $sections[] = new TomlSection($table, [...$lines, '']);
                $replaced = true;
            }
        }
        $this->sections = $sections;
        if (!$replaced) {
            $this->append(new TomlSection($table, [...$lines, '']));
        }
    }

    public function removeTable(string $table): void
    {
        $kept = [$this->sections[0]];
        foreach (\array_slice($this->sections, 1) as $section) {
            if (!$this->isWithin($section->name, $table)) {
                $kept[] = $section;
            }
        }
        $this->sections = $kept;
    }

    public function setKey(string $table, string $key, string $line): void
    {
        foreach ($this->sections as $section) {
            if ($section->name === $table) {
                $section->setKey($key, $line);

                return;
            }
        }

        $this->append(new TomlSection($table, [\sprintf('[%s]', $table), $line, '']));
    }

    public function removeKey(string $table, string $key): void
    {
        foreach ($this->sections as $section) {
            if ($section->name === $table) {
                $section->removeKey($key);
            }
        }
    }

    private function append(TomlSection $table): void
    {
        $last = $this->sections[\count($this->sections) - 1];
        if (1 === \count($this->sections) && $last->isEmpty()) {
            $last->clear();
        } elseif (!$last->endsWithBlankLine()) {
            $last->add('');
        }
        $this->sections[] = $table;
    }

    private function isWithin(string $section, string $table): bool
    {
        return $section === $table || str_starts_with($section, $table.'.');
    }

    private function relativeName(string $table, string $section): ?string
    {
        if ('' === $section) {
            return $table;
        }

        return str_starts_with($table, $section.'.') ? substr($table, \strlen($section) + 1) : null;
    }

    private static function header(string $line): ?string
    {
        if (1 === preg_match(self::TABLE, $line, $match)) {
            return self::normalize($match[1]);
        }
        if (1 === preg_match(self::ARRAY_OF_TABLES, $line, $match)) {
            return '[['.self::normalize($match[1]);
        }

        return null;
    }

    private static function normalize(string $name): string
    {
        $dotted = preg_replace('/\s*\.\s*/', '.', trim($name)) ?? $name;

        return str_replace(['"', "'"], '', $dotted);
    }

    /**
     * The multi-line string delimiter still open after this line, if any: a header inside such a string is text.
     */
    private static function openStringAfter(string $line, ?string $open): ?string
    {
        if (null !== $open) {
            return 1 === substr_count($line, $open) % 2 ? null : $open;
        }
        foreach (['"""', "'''"] as $delimiter) {
            if (1 === substr_count($line, $delimiter) % 2) {
                return $delimiter;
            }
        }

        return null;
    }
}
