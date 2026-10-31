<?php

declare(strict_types=1);

namespace App\Sandbox;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where a confined command may write, and what it must not read.
 */
final readonly class SandboxPaths
{
    /** Under the home, where installs and test runs write what they download and build. */
    private const array CACHES = [
        '.cache', '.composer', '.config/composer', '.local/share/composer', '.npm', '.yarn', '.pnpm-store',
        '.local/share/pnpm', '.cargo/registry', '.cargo/git', 'go/pkg', '.gradle', '.m2', '.bundle', '.gem', '.nuget',
        'Library/Caches',
    ];

    /** Under the home, where the user's keys and tokens live. */
    private const array KEYS = [
        '.ssh', '.gnupg', '.aws', '.azure', '.kube', '.config/gcloud', '.config/gh', '.netrc', '.git-credentials',
        '.pypirc', '.docker/config.json',
    ];

    /** Under Airlock's own directory, what holds its secrets. */
    private const array AIRLOCK_SECRETS = ['.env.local', '.env.*.local', 'config/secrets'];

    public function __construct(
        #[Autowire(env: 'HOME')]
        private string $home,
        #[Autowire(param: 'kernel.project_dir')]
        private string $airlockDirectory,
        #[Autowire(env: 'default::XDG_CONFIG_HOME')]
        private ?string $configHome = null,
    ) {
    }

    /**
     * Existing directories a command may write to besides the project.
     *
     * @return list<string>
     */
    public function writable(): array
    {
        $candidates = ['/tmp', '/var/tmp', sys_get_temp_dir()];
        foreach (self::CACHES as $cache) {
            $candidates[] = $this->home.'/'.$cache;
        }

        return $this->existing($candidates);
    }

    /**
     * Existing files and directories a command must not read: the user's keys and Airlock's own configuration.
     *
     * @return list<string>
     */
    public function hidden(): array
    {
        $candidates = [$this->airlockConfigDirectory()];
        foreach (self::KEYS as $key) {
            $candidates[] = $this->home.'/'.$key;
        }
        foreach (self::AIRLOCK_SECRETS as $pattern) {
            $matches = glob($this->airlockDirectory.'/'.$pattern);
            if (false !== $matches) {
                array_push($candidates, ...$matches);
            }
        }

        return $this->existing($candidates);
    }

    private function airlockConfigDirectory(): string
    {
        $configHome = null === $this->configHome || '' === $this->configHome ? $this->home.'/.config' : $this->configHome;

        return $configHome.'/airlock';
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function existing(array $paths): array
    {
        $existing = [];
        foreach ($paths as $path) {
            $real = realpath($path);
            if (false !== $real) {
                $existing[] = $real;
            }
        }

        return array_values(array_unique($existing));
    }
}
