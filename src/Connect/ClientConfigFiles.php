<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads and writes clients' configuration files, and keeps each one as it was before Airlock first changed it, so
 * that disconnecting takes back what Airlock added and nothing the user had.
 */
final readonly class ClientConfigFiles
{
    public function __construct(
        private ConfigSyntaxes $syntaxes,
        private ConnectionOriginals $originals,
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
        $syntax = $this->syntaxes->for($edit->format);
        $changes = array_map(static fn (ConfigChange $change): string => $change->describe($direction), $edit->changes);
        if (Direction::Connect === $direction) {
            return new PlannedFile($edit->path, $exists, $before, $syntax->connect($before, $edit->changes), $changes);
        }

        $original = $this->originals->of($edit->path);
        $left = [];
        if (null === $original) {
            foreach ($edit->changes as $change) {
                if ($change->isShared()) {
                    $left[] = $change->path()->dotted();
                }
            }
        }

        return new PlannedFile($edit->path, $exists, $before, $syntax->disconnect($before, $edit->changes, $original), $changes, $left);
    }

    public function write(PlannedFile $file, Direction $direction): void
    {
        if (Direction::Connect === $direction) {
            $this->originals->keep($file->path, $file->exists, $file->before);
        }
        if ($file->isChanged()) {
            $this->filesystem->dumpFile($file->path, $file->after);
        }
        if (Direction::Disconnect === $direction) {
            $this->originals->forget($file->path);
        }
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
