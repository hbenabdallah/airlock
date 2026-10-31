<?php

declare(strict_types=1);

namespace App\Output;

/**
 * Text that came from outside, an output or a command line, put between tags it cannot close: any tag of the same
 * name inside it, opening or closing, is written with `&lt;`, so what follows a forged `</command-output>` is still
 * inside the real one.
 */
final readonly class DataTag
{
    /**
     * @param non-empty-string      $name
     * @param array<string, string> $attributes
     */
    #[\NoDiscard('only the wrapped text is safe to show')]
    public static function wrap(string $name, string $content, array $attributes = []): string
    {
        $opening = $name;
        foreach ($attributes as $attribute => $value) {
            $opening .= \sprintf(' %s="%s"', $attribute, htmlspecialchars($value, \ENT_QUOTES));
        }
        $inert = preg_replace('/<(?=\s*\/?\s*'.preg_quote($name, '/').'\b)/i', '&lt;', $content) ?? str_replace('<', '&lt;', $content);
        $separator = '' === $inert || str_ends_with($inert, "\n") ? '' : "\n";

        return \sprintf("<%s>\n%s%s</%s>", $opening, $inert, $separator, $name);
    }
}
