<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A file read as a tree of tables, JSON or YAML: decoded, changed, and encoded again only when something changed.
 */
abstract readonly class ConfigTree implements ConfigSyntax
{
    #[\Override]
    public function connect(string $content, array $changes): string
    {
        $root = $this->decode($content);
        foreach ($changes as $change) {
            $change->connect($root);
        }

        return $this->encoded($content, $root);
    }

    #[\Override]
    public function disconnect(string $content, array $changes, ?string $original): string
    {
        $root = $this->decode($content);
        $originalRoot = null === $original ? null : $this->decode($original);
        foreach ($changes as $change) {
            $change->disconnect($root, $originalRoot);
        }

        return $this->encoded($content, $root);
    }

    /**
     * @throws UnsupportedConfigException
     */
    abstract protected function decode(string $content): \stdClass;

    abstract protected function encode(\stdClass $root): string;

    private function encoded(string $content, \stdClass $root): string
    {
        $after = $this->encode($root);

        return $after === $this->encode($this->decode($content)) ? $content : $after;
    }
}
