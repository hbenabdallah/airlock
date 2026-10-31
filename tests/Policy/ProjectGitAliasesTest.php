<?php

declare(strict_types=1);

namespace App\Tests\Policy;

use App\Execution\ProjectDirectory;
use App\Policy\ProjectGitAliases;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Aliases read from a real repository, with git itself.
 */
final class ProjectGitAliasesTest extends TestCase
{
    private const string PROJECT = __DIR__.'/../../var/test-git-aliases';

    private ProjectGitAliases $aliases;

    #[\Override]
    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(self::PROJECT);
        $filesystem->mkdir(self::PROJECT);
        new Process(['git', 'init', '-q'], self::PROJECT)->mustRun();
        new Process(['git', 'config', 'alias.p', 'push --force'], self::PROJECT)->mustRun();
        $this->aliases = new ProjectGitAliases(new ProjectDirectory(self::PROJECT));
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove(self::PROJECT);
    }

    public function testAnAliasOfTheProjectIsRead(): void
    {
        self::assertSame('push --force', $this->aliases->definition('p'));
    }

    public function testAWordThatIsNoAliasHasNoDefinition(): void
    {
        self::assertNull($this->aliases->definition('status'));
    }

    public function testANameGitCouldNotUseAsAnAliasIsNotLookedUp(): void
    {
        self::assertNull($this->aliases->definition('p.x'));
    }
}
