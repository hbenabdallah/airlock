<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * `%env(process_key:NAME)%`: the variable when it holds a key long enough to sign with, or else a random key made once
 * for this process. A client starts its own Airlock, so a state signed by one process is only ever checked by it.
 */
final class ProcessKeyEnvVarProcessor implements EnvVarProcessorInterface
{
    private const int KEY_BYTES = 32;

    private ?string $randomKey = null;

    #[\Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $configured = $getEnv($name);
        if (\is_string($configured) && \strlen($configured) >= self::KEY_BYTES) {
            return $configured;
        }

        return $this->randomKey ??= bin2hex(random_bytes(self::KEY_BYTES));
    }

    #[\Override]
    public static function getProvidedTypes(): array
    {
        return ['process_key' => 'string'];
    }
}
