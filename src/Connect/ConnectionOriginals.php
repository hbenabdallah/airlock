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
     * Keeps the file as it is now, unless Airlock already kept it: the first original is the one that counts. The
     * record appears whole or not at all, and readable by its owner only from the start.
     */
    public function keep(string $path, bool $exists, string $content): void
    {
        $record = $this->recordPath($path);
        if (null !== $this->of($path)) {
            return;
        }

        $directory = \dirname($record);
        $this->filesystem->mkdir($directory, 0o700);
        $temporary = $this->filesystem->tempnam($directory, '.original-');
        $this->filesystem->chmod($temporary, 0o600);
        $this->filesystem->appendToFile($temporary, json_encode(['path' => $path, 'existed' => $exists, 'content' => $content], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
        $this->filesystem->rename($temporary, $record, true);
    }

    /**
     * The file as it was, '' when it did not exist, null when Airlock has no record of it it can read.
     */
    public function of(string $path): ?string
    {
        $record = $this->recordPath($path);
        if (!is_file($record) || !is_readable($record)) {
            return null;
        }
        $content = file_get_contents($record);
        if (false === $content) {
            return null;
        }

        try {
            $kept = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
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
