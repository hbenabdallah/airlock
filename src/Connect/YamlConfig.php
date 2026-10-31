<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * A YAML file. Rewriting it drops its comments, which is why Airlock keeps a copy of the file before it writes.
 */
final readonly class YamlConfig implements ConfigSyntax
{
    #[\Override]
    public function edit(string $content, array $changes, Direction $direction): string
    {
        $before = $this->decode($content);
        $after = $this->decode($content);
        ConfigTree::edit($after, $changes, $direction);

        return $this->encode($after) === $this->encode($before) ? $content : $this->encode($after);
    }

    private function decode(string $content): \stdClass
    {
        if ('' === trim($content)) {
            return new \stdClass();
        }

        try {
            $root = Yaml::parse($content, Yaml::PARSE_OBJECT_FOR_MAP);
        } catch (ParseException $e) {
            throw new UnsupportedConfigException(\sprintf('it is not valid YAML (%s)', $e->getMessage()), $e->getCode(), previous: $e);
        }
        if (!$root instanceof \stdClass) {
            throw new UnsupportedConfigException('it does not hold a YAML map');
        }

        return $root;
    }

    private function encode(\stdClass $root): string
    {
        return Yaml::dump($root, 10, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
