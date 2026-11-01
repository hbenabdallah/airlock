<?php

declare(strict_types=1);

namespace App\Connect;

use App\Configuration\ConfigDirectory;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Each client file as it was before Airlock first changed it, kept in Airlock's own directory, out of every
 * command's reach and readable by its owner only, since some of these files hold the client's credentials.
 * Disconnecting reads it to put back what Airlock found.
 */
final readonly class ConnectionOriginals
{
    public function __construct(
        private ConfigDirectory $configDirectory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Keeps the file as it is now, unless Airlock already kept it: the first original is the one that counts.
     */
    public function keep(string $path, bool $exists, string $content): void
    {
        $record = $this->recordPath($path);
        if (file_exists($record)) {
            return;
        }

        $this->filesystem->dumpFile($record, '');
        $this->filesystem->chmod($record, 0o600);
        $this->filesystem->dumpFile($record, json_encode(['path' => $path, 'existed' => $exists, 'content' => $content], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }

    /**
     * The file as it was, '' when it did not exist, null when Airlock has no record of it.
     */
    public function of(string $path): ?string
    {
        $record = $this->recordPath($path);
        if (!is_file($record)) {
            return null;
        }

        try {
            $kept = json_decode((string) file_get_contents($record), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($kept) && \is_string($kept['content'] ?? null) ? $kept['content'] : null;
    }

    public function forget(string $path): void
    {
        $this->filesystem->remove($this->recordPath($path));
    }

    public function recordPath(string $path): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($path)) ?? 'file';

        return \sprintf('%s/originals/%s-%s.json', $this->configDirectory->path, $name, substr(hash('sha256', $path), 0, 12));
    }
}
