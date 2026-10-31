<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Configuration\ConfigDirectory;
use App\Configuration\ModelSetting;
use App\Configuration\ModelSettings;
use App\Configuration\ModelSettingsFile;
use App\Tests\Output\RecordedModelApi;
use App\Tests\Output\RecordedResponses;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class ConfigureCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string CONFIG_HOME = __DIR__.'/../../var/test-configure';

    private ModelSettingsFile $file;

    /** @var array<mixed> */
    private array $server;

    /** @var array<mixed> */
    private array $environment;

    #[\Override]
    protected function setUp(): void
    {
        new Filesystem()->remove(self::CONFIG_HOME);
        $this->server = $_SERVER;
        $this->environment = $_ENV;
        $_SERVER['XDG_CONFIG_HOME'] = $_ENV['XDG_CONFIG_HOME'] = self::CONFIG_HOME;
        $this->file = new ModelSettingsFile(new ConfigDirectory('/nonexistent', self::CONFIG_HOME));
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->environment;
        new Filesystem()->remove(self::CONFIG_HOME);
        parent::tearDown();
    }

    public function testAModelFitForBothChecksIsFoundByCalibratingWhatTheApiLists(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key']);
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertEquals(new ModelSettings(RecordedResponses::BASE_URL, 'secret-key', 'test/output-check', 'test/output-check'), $this->file->read());
        self::assertStringContainsString('test/blind  output check: it missed a known prompt injection', $tester->getDisplay());
        self::assertStringNotContainsString('secret-key', $tester->getDisplay());
    }

    public function testWithoutAModelFitForBothEachCheckTakesTheFirstFitForIt(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key']);
        $status = $tester->execute(['--max-probes' => '1']);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertSame(RecordedModelApi::BLIND, $this->file->read()->riskModel);
        self::assertSame('', $this->file->read()->outputCheckModel, 'No probed model passed the output check, and the input ended before the user named one.');
    }

    public function testAModelThatGivesNoAnswerDoesNotUseUpTheProbes(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key']);
        $tester->execute(['--max-probes' => '1']);

        self::assertStringContainsString(RecordedModelApi::MISSING.'  the model could not be reached or gave no usable answer', $tester->getDisplay());
        self::assertSame(RecordedModelApi::BLIND, $this->file->read()->riskModel, 'The listed model after the unanswered one is calibrated within a budget of one.');
    }

    public function testWhenNoListedModelPassesTheUserIsAskedForOne(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key', 'test/output-check']);
        $status = $tester->execute(['--max-probes' => '1']);

        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());
        self::assertSame('test/output-check', $this->file->read()->outputCheckModel);
        self::assertStringContainsString('No listed model passed for the output check.', $tester->getDisplay());
    }

    public function testAModelThatFailsIsKeptOnlyIfTheUserInsists(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key', 'no']);
        $tester->execute(['--output-check-model' => RecordedModelApi::BLIND, '--risk-model' => 'test/risk']);

        self::assertSame('', $this->file->read()->outputCheckModel);
        self::assertSame('test/risk', $this->file->read()->riskModel);
    }

    public function testARateLimitedModelIsTriedOnceMoreAfterAPause(): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, 'secret-key']);
        $tester->execute(['--output-check-model' => RecordedModelApi::LIMITED, '--risk-model' => 'test/risk']);

        self::assertSame('2026-11-01 10:00:30', $clock->now()->format('Y-m-d H:i:s'));
        self::assertStringContainsString('rate limited: waiting 30 seconds before trying once more', $tester->getDisplay());
        self::assertSame('', $this->file->read()->outputCheckModel);
    }

    public function testCheckCalibratesWhatIsConfiguredAndChangesNothing(): void
    {
        $settings = new ModelSettings(RecordedResponses::BASE_URL, 'secret-key', RecordedModelApi::BLIND, 'test/risk');
        $this->file->write($settings);
        $this->withoutModelEnvironmentVariables();
        $tester = $this->tester();

        $status = $tester->execute(['--check' => true]);

        self::assertSame(Command::FAILURE, $status);
        self::assertEquals($settings, $this->file->read());
    }

    public function testCheckWaitsOutARateLimitBeforeItJudges(): void
    {
        $clock = self::mockTime('2026-11-01 10:00:00');
        $this->file->write(new ModelSettings(RecordedResponses::BASE_URL, 'secret-key', RecordedModelApi::LIMITED, 'test/risk'));
        $this->withoutModelEnvironmentVariables();
        $tester = $this->tester();

        $tester->execute(['--check' => true]);

        self::assertSame('2026-11-01 10:00:30', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testCheckCalibratesTheModelsTheEnvironmentSetsOverTheFile(): void
    {
        $this->file->write(new ModelSettings(RecordedResponses::BASE_URL, 'secret-key', RecordedModelApi::BLIND, 'test/risk'));
        $_SERVER['AIRLOCK_OUTPUT_CHECK_MODEL'] = $_ENV['AIRLOCK_OUTPUT_CHECK_MODEL'] = 'test/output-check';
        $tester = $this->tester();

        $status = $tester->execute(['--check' => true]);

        self::assertSame(Command::SUCCESS, $status, 'AIRLOCK_OUTPUT_CHECK_MODEL names a fit model, and it wins over the blind one in the file.');
        self::assertStringContainsString('test/output-check  output check: passes', $tester->getDisplay());
    }

    public function testWithoutAKeyNothingIsSaved(): void
    {
        $tester = $this->tester();

        $tester->setInputs([RecordedResponses::BASE_URL, '']);
        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertFileDoesNotExist($this->file->path());
    }

    private function withoutModelEnvironmentVariables(): void
    {
        foreach (ModelSetting::cases() as $setting) {
            $_SERVER[$setting->environmentVariable()] = $_ENV[$setting->environmentVariable()] = '';
        }
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('configure'));
    }
}
