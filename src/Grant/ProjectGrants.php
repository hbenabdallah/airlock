<?php

declare(strict_types=1);

namespace App\Grant;

use App\Configuration\ConfigDirectory;
use App\Execution\ProjectDirectory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Grants kept for the project, in Airlock's directory, where no command can read or change them. Written under a
 * lock: two clients can share one project, each with its own Airlock.
 */
final readonly class ProjectGrants
{
    public function __construct(
        private ConfigDirectory $configDirectory,
        private ProjectDirectory $projectDirectory,
        private LockFactory $lockFactory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return list<Grant>
     *
     * @throws InvalidGrantsException when the file cannot be read: it is never guessed at
     */
    public function all(): array
    {
        $file = $this->file();
        if (!is_file($file)) {
            return [];
        }

        try {
            $content = Yaml::parseFile($file);
        } catch (ParseException $e) {
            throw new InvalidGrantsException(\sprintf('The grants file %s is not valid YAML: %s', $file, $e->getMessage()), $e->getCode(), previous: $e);
        }

        return $this->grantsIn($content, $file);
    }

    /**
     * @param list<Grant> $grants
     */
    public function add(array $grants): void
    {
        $lock = $this->lockFactory->createLock('airlock-grants-'.hash('sha256', $this->file()));
        $lock->acquire(blocking: true);
        try {
            $kept = $this->all();
            foreach ($grants as $grant) {
                if (!array_any($kept, $grant->equals(...))) {
                    $kept[] = $grant;
                }
            }
            $this->filesystem->dumpFile($this->file(), Yaml::dump(['grants' => array_map($this->normalize(...), $kept)], 3));
        } finally {
            $lock->release();
        }
    }

    private function file(): string
    {
        return $this->configDirectory->forProject($this->projectDirectory->path).'/grants.yaml';
    }

    /**
     * @return array{command: list<string>, prefix: bool}
     */
    private function normalize(Grant $grant): array
    {
        return ['command' => $grant->words, 'prefix' => $grant->coversLongerCommands];
    }

    /**
     * @return list<Grant>
     */
    private function grantsIn(mixed $content, string $file): array
    {
        $entries = \is_array($content) && \array_key_exists('grants', $content) ? $content['grants'] : null;
        if (!\is_array($entries)) {
            throw new InvalidGrantsException(\sprintf('The grants file %s holds no "grants" list.', $file));
        }

        $grants = [];
        foreach ($entries as $entry) {
            $grants[] = $this->denormalize($entry, $file);
        }

        return $grants;
    }

    private function denormalize(mixed $entry, string $file): Grant
    {
        $invalid = new InvalidGrantsException(\sprintf('The grants file %s holds an entry that is not {command: [words], prefix: true|false}.', $file));
        $command = \is_array($entry) ? ($entry['command'] ?? null) : null;
        $prefix = \is_array($entry) ? ($entry['prefix'] ?? null) : null;
        if (!\is_array($command) || !array_is_list($command) || !\is_bool($prefix)) {
            throw $invalid;
        }

        $words = [];
        foreach ($command as $word) {
            if (!\is_string($word)) {
                throw $invalid;
            }
            $words[] = $word;
        }
        if ([] === $words) {
            throw $invalid;
        }

        return new Grant($words, $prefix);
    }
}
