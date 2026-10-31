<?php

declare(strict_types=1);

namespace App\Tests\Policy;

use App\Configuration\ConfigDirectory;
use App\Execution\ProjectDirectory;
use App\Policy\InvalidPolicyException;
use App\Policy\PolicyFiles;
use App\Policy\RefusedCommand;
use App\Shell\ShellParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class PolicyFilesTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../var/test-policy';

    private Filesystem $filesystem;

    private ConfigDirectory $configDirectory;

    private string $project;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(self::ROOT);
        $this->filesystem->mkdir([self::ROOT.'/home', self::ROOT.'/project']);
        $this->configDirectory = new ConfigDirectory((string) realpath(self::ROOT.'/home'));
        $this->project = (string) realpath(self::ROOT.'/project');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove(self::ROOT);
    }

    public function testWithoutAnyPolicyFileNothingIsRefused(): void
    {
        self::assertSame([], $this->refusals());
    }

    public function testTheUsersPolicyTheProjectsPolicyAndTheCommittedOneAddUp(): void
    {
        $this->filesystem->dumpFile($this->configDirectory->path.'/policy.yaml', "refuse:\n  - terraform destroy\n");
        $this->filesystem->dumpFile($this->configDirectory->forProject($this->project).'/policy.yaml', "refuse: ['git push --force']\n");
        $this->filesystem->dumpFile($this->project.'/.airlock.yaml', "refuse:\n  - 'docker system prune'\n");

        $refused = array_map(static fn (RefusedCommand $refusal): string => implode(' ', $refusal->words), $this->refusals());

        self::assertSame(['terraform destroy', 'git push --force', 'docker system prune'], $refused);
    }

    public function testEachRefusalSaysWhichFileItComesFrom(): void
    {
        $this->filesystem->dumpFile($this->project.'/.airlock.yaml', "refuse: ['make deploy']\n");

        self::assertSame($this->project.'/.airlock.yaml', $this->refusals()[0]->source);
    }

    public function testAnEmptyFileRefusesNothing(): void
    {
        $this->filesystem->dumpFile($this->project.'/.airlock.yaml', '');

        self::assertSame([], $this->refusals());
    }

    #[DataProvider('invalidFiles')]
    public function testAnInvalidFileStopsEverythingUntilItIsFixed(string $content): void
    {
        $this->filesystem->dumpFile($this->project.'/.airlock.yaml', $content);

        $this->expectException(InvalidPolicyException::class);
        $this->expectExceptionMessageIsOrContains($this->project.'/.airlock.yaml');

        $this->refusals();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'not YAML' => ["refuse: [unclosed\n"];
        yield 'a grant, which a policy cannot hold' => ["grant: ['rm -rf *']\n"];
        yield 'a refusal that is not a list' => ["refuse: rm\n"];
        yield 'two commands in one refusal' => ["refuse: ['a; b']\n"];
        yield 'an opaque refusal' => ["refuse: ['rm $(x)']\n"];
        yield 'an empty refusal' => ["refuse: ['']\n"];
    }

    /**
     * @return list<RefusedCommand>
     */
    private function refusals(): array
    {
        return new PolicyFiles($this->configDirectory, new ProjectDirectory($this->project), new ShellParser())->refusals();
    }
}
