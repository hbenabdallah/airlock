<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Output\InjectionClassifier;
use App\Output\OutputVerdict;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ModelApiConfigurationTest extends KernelTestCase
{
    private const string BASE_URL = 'https://models.example.test';

    #[\Override]
    protected function setUp(): void
    {
        $_SERVER['AIRLOCK_AI_BASE_URL'] = $_ENV['AIRLOCK_AI_BASE_URL'] = self::BASE_URL;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER['AIRLOCK_AI_BASE_URL'], $_ENV['AIRLOCK_AI_BASE_URL']);
    }

    public function testTheModelsAreAskedThroughTheConfiguredApi(): void
    {
        $check = self::getContainer()->get(InjectionClassifier::class)->check("Build finished.\n");

        self::assertSame(OutputVerdict::Clean, $check->verdict, 'The recorded API answers only at the configured base URL.');
    }
}
