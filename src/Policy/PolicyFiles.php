<?php

declare(strict_types=1);

namespace App\Policy;

use App\Configuration\ConfigDirectory;
use App\Execution\ProjectDirectory;
use App\Shell\ShellParser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads what is refused, from three files, every time: they can change while Airlock runs.
 *
 * Two are the user's, in Airlock's directory, out of every command's reach: `policy.yaml` for every project, and one
 * per project. The third, `.airlock.yaml`, is committed with the project so a team shares it; a command can write to
 * it, so it can only add refusals, never lift one or grant anything.
 */
final readonly class PolicyFiles
{
    public const string COMMITTED_FILE = '.airlock.yaml';

    public function __construct(
        private ConfigDirectory $configDirectory,
        private ProjectDirectory $projectDirectory,
        private ShellParser $parser,
    ) {
    }

    /**
     * @return list<RefusedCommand>
     *
     * @throws InvalidPolicyException when a policy file cannot be read: nothing runs until it is fixed
     */
    public function refusals(): array
    {
        $project = $this->projectDirectory->path;
        $files = [
            $this->configDirectory->path.'/policy.yaml',
            $this->configDirectory->forProject($project).'/policy.yaml',
            $project.'/'.self::COMMITTED_FILE,
        ];

        $refusals = [];
        foreach ($files as $file) {
            array_push($refusals, ...$this->read($file));
        }

        return $refusals;
    }

    /**
     * @return list<RefusedCommand>
     */
    private function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        try {
            $content = Yaml::parseFile($file);
            $policy = new Processor()->processConfiguration(new PolicyConfiguration(), null === $content ? [] : [$content]);
        } catch (ParseException|InvalidConfigurationException $e) {
            throw new InvalidPolicyException(\sprintf('The policy file %s is invalid: %s', $file, $e->getMessage()), $e->getCode(), previous: $e);
        }

        $refusals = [];
        $refused = $policy['refuse'] ?? [];
        foreach (\is_array($refused) ? $refused : [] as $commandLine) {
            if (\is_string($commandLine)) {
                $refusals[] = $this->refusedCommand($commandLine, $file);
            }
        }

        return $refusals;
    }

    private function refusedCommand(string $refused, string $file): RefusedCommand
    {
        $line = $this->parser->parse($refused);
        $words = 1 === \count($line->commands) && !$line->isOpaque() ? $line->commands[0]->programWords() : [];
        if ([] === $words) {
            throw new InvalidPolicyException(\sprintf('The policy file %s refuses "%s", which is not one plain command.', $file, $refused));
        }

        return new RefusedCommand($words, $file);
    }
}
