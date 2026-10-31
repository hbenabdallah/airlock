<?php

declare(strict_types=1);

namespace App\Tests\Configuration;

use App\Configuration\ConfigDirectory;
use App\Configuration\ModelSettingEnvVarProcessor;
use App\Configuration\ModelSettings;
use App\Configuration\ModelSettingsFile;
use App\Tests\Output\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ModelSettingEnvVarProcessorTest extends TestCase
{
    private const string HOME = __DIR__.'/../../var/test-model-setting-processor';

    private ModelSettingsFile $file;

    private RecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(self::HOME);
        $this->file = new ModelSettingsFile(new ConfigDirectory(self::HOME));
        $this->logger = new RecordingLogger();
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove(self::HOME);
    }

    public function testTheEnvironmentWinsOverTheFile(): void
    {
        $this->file->write(new ModelSettings(apiKey: 'from-the-file'));

        self::assertSame('from-the-environment', $this->resolve('AIRLOCK_AI_API_KEY', 'from-the-environment'));
    }

    public function testAnEmptyVariableFallsBackToTheFile(): void
    {
        $this->file->write(new ModelSettings(riskModel: 'test/risk'));

        self::assertSame('test/risk', $this->resolve('AIRLOCK_RISK_MODEL', ''));
    }

    public function testWithNeitherTheSettingIsEmpty(): void
    {
        self::assertSame('', $this->resolve('AIRLOCK_AI_BASE_URL', ''));
    }

    public function testAFileThatCannotBeReadLeavesTheChecksOffAndIsLogged(): void
    {
        new Filesystem()->dumpFile($this->file->path(), "base_url: [unclosed\n");

        self::assertSame('', $this->resolve('AIRLOCK_AI_BASE_URL', ''));
        self::assertSame('warning', $this->logger->records[0]['level'] ?? null);
    }

    public function testOnlyModelSettingsAreResolved(): void
    {
        $this->expectException(\LogicException::class);

        $this->resolve('AIRLOCK_TIMEOUT', '120');
    }

    private function resolve(string $name, string $environment): string
    {
        return new ModelSettingEnvVarProcessor($this->file, $this->logger)->getEnv('model_setting', $name, static fn (): string => $environment);
    }
}
