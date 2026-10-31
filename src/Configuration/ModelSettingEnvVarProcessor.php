<?php

declare(strict_types=1);

namespace App\Configuration;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * `%env(model_setting:AIRLOCK_AI_API_KEY)%`: the environment variable when it is set, else what `airlock configure`
 * wrote in `models.yaml`. A file that cannot be read leaves the model checks off, never Airlock.
 */
final class ModelSettingEnvVarProcessor implements EnvVarProcessorInterface
{
    private ?ModelSettings $fileSettings = null;

    public function __construct(
        private readonly ModelSettingsFile $file,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $setting = ModelSetting::forEnvironmentVariable($name);
        if (!$setting instanceof ModelSetting) {
            throw new \LogicException(\sprintf('"%s" is not a model setting.', $name));
        }

        $fromEnvironment = $getEnv($name);
        if (\is_string($fromEnvironment) && '' !== $fromEnvironment) {
            return $fromEnvironment;
        }

        return $this->fileSettings()->get($setting);
    }

    #[\Override]
    public static function getProvidedTypes(): array
    {
        return ['model_setting' => 'string'];
    }

    private function fileSettings(): ModelSettings
    {
        if ($this->fileSettings instanceof ModelSettings) {
            return $this->fileSettings;
        }

        try {
            $this->fileSettings = $this->file->read();
        } catch (InvalidModelSettingsException $e) {
            $this->logger->warning('Airlock leaves the model checks off: {reason}', ['reason' => $e->getMessage()]);
            $this->fileSettings = new ModelSettings();
        }

        return $this->fileSettings;
    }
}
