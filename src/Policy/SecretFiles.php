<?php

declare(strict_types=1);

namespace App\Policy;

/**
 * Recognises, by name, the files that usually hold secrets: a `.env` is a secret before anyone has looked inside it.
 * Templates of them (`.env.example`, `.env.dist`) are not.
 */
final class SecretFiles
{
    private const array ENV_TEMPLATES = ['example', 'sample', 'dist', 'template', 'defaults'];

    private const array NAMES = [
        '.envrc', '.netrc', '_netrc', '.npmrc', '.pypirc', '.git-credentials', '.htpasswd', '.pgpass', '.vault-token',
        'id_rsa', 'id_dsa', 'id_ecdsa', 'id_ed25519', 'credentials', 'credentials.json', 'auth.json', 'secring.gpg',
    ];

    private const array EXTENSIONS = ['pem', 'key', 'p12', 'pfx', 'jks', 'keystore', 'kdbx', 'ovpn'];

    /** Directories whose every file is a secret. */
    private const array DIRECTORIES = ['.ssh', '.aws', '.gnupg', '.kube', '.azure'];

    public function isSecret(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        $name = strtolower(basename($path));
        if (\in_array($name, ['', '.', '..'], true)) {
            return false;
        }

        return $this->isEnvFile($name)
            || \in_array($name, self::NAMES, true)
            || \in_array(pathinfo($name, \PATHINFO_EXTENSION), self::EXTENSIONS, true)
            || 1 === preg_match('/^(client_)?secrets?([._-][^\/]*)?\.(json|ya?ml|txt|env|ini|toml|xml)$/', $name)
            || 1 === preg_match('/^service[-_]?account.*\.json$/', $name)
            || [] !== array_intersect(\array_slice(explode('/', $path), 0, -1), self::DIRECTORIES);
    }

    private function isEnvFile(string $name): bool
    {
        if ('.env' === $name) {
            return true;
        }

        return str_starts_with($name, '.env.') && !\in_array(substr($name, 5), self::ENV_TEMPLATES, true);
    }
}
