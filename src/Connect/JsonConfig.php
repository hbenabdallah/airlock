<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A plain JSON file. A file with comments or trailing commas is left to the user: decoding it would drop them.
 */
final readonly class JsonConfig implements ConfigSyntax
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
            $root = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnsupportedConfigException(\sprintf('it is not plain JSON (%s): comments and trailing commas are not supported', $e->getMessage()), $e->getCode(), previous: $e);
        }
        if (!$root instanceof \stdClass) {
            throw new UnsupportedConfigException('it does not hold a JSON object');
        }

        return $root;
    }

    private function encode(\stdClass $root): string
    {
        return json_encode($root, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR)."\n";
    }
}
