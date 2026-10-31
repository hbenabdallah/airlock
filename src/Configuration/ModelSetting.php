<?php

declare(strict_types=1);

namespace App\Configuration;

/**
 * One setting of the model checks, read from its environment variable when set, else from `models.yaml`.
 */
enum ModelSetting: string
{
    case BaseUrl = 'base_url';
    case ApiKey = 'api_key';
    case OutputCheckModel = 'output_check_model';
    case RiskModel = 'risk_model';

    public function environmentVariable(): string
    {
        return match ($this) {
            self::BaseUrl => 'AIRLOCK_AI_BASE_URL',
            self::ApiKey => 'AIRLOCK_AI_API_KEY',
            self::OutputCheckModel => 'AIRLOCK_OUTPUT_CHECK_MODEL',
            self::RiskModel => 'AIRLOCK_RISK_MODEL',
        };
    }

    public static function forEnvironmentVariable(string $name): ?self
    {
        foreach (self::cases() as $setting) {
            if ($setting->environmentVariable() === $name) {
                return $setting;
            }
        }

        return null;
    }
}
