<?php

declare(strict_types=1);

namespace App\Output;

/**
 * Replaces the secrets a command printed with `[masked: <kind>]`, before anyone reads its output: the agent, the model
 * that checks it, the audit log.
 */
final readonly class SecretMasker
{
    public const string WITHHELD = '[Airlock could not scan this output for secrets: it is withheld]';

    #[\NoDiscard('the text to show is the masked one')]
    public function mask(string $text): MaskedText
    {
        $masked = 0;
        foreach (SecretKind::cases() as $kind) {
            $text = preg_replace($kind->pattern(), $kind->mask(), $text, count: $count);
            if (null === $text) {
                return new MaskedText(self::WITHHELD, 0);
            }
            $masked += $count;
        }

        return new MaskedText($text, $masked);
    }
}
