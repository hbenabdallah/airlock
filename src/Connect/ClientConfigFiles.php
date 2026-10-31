<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads and writes clients' configuration files. The first time Airlock changes a file, it keeps the file as it was
 * next to it, with the same permissions: some of these files hold the client's credentials.
 */
final readonly class ClientConfigFiles
{
    public const string BACKUP_SUFFIX = '.airlock-backup';

    public function __construct(
        private ConfigSyntaxes $syntaxes,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @throws UnsupportedConfigException when the file cannot be edited without risking what else it holds
     */
    public function plan(FileEdit $edit, Direction $direction): PlannedFile
    {
        $exists = file_exists($edit->path);
        $before = $exists ? $this->read($edit->path) : '';
        $after = $this->syntaxes->for($edit->format)->edit($before, $edit->changes, $direction);
        $changes = array_map(static fn (ConfigChange $change): string => $change->describe($direction), $edit->changes);

        return new PlannedFile($edit->path, $exists, $before, $after, $changes);
    }

    public function write(PlannedFile $file): void
    {
        if (!$file->isChanged()) {
            return;
        }

        $backup = $file->path.self::BACKUP_SUFFIX;
        if ($file->exists && !file_exists($backup)) {
            $this->filesystem->dumpFile($backup, $file->before);
            $this->filesystem->chmod($backup, fileperms($file->path) & 0o777);
        }
        $this->filesystem->dumpFile($file->path, $file->after);
    }

    private function read(string $path): string
    {
        if (!is_file($path)) {
            throw new UnsupportedConfigException('it is not a file');
        }
        $content = file_get_contents($path);
        if (false === $content) {
            throw new \RuntimeException(\sprintf('Airlock cannot read %s.', $path));
        }

        return $content;
    }
}
