<?php

declare(strict_types=1);

namespace App\Configuration;

/**
 * The model checks' settings as `airlock configure` writes them: an empty string is a setting not given.
 */
final readonly class ModelSettings
{
    public function __construct(
        public string $baseUrl = '',
        #[\SensitiveParameter]
        public string $apiKey = '',
        public string $outputCheckModel = '',
        public string $riskModel = '',
    ) {
    }

    public function get(ModelSetting $setting): string
    {
        return match ($setting) {
            ModelSetting::BaseUrl => $this->baseUrl,
            ModelSetting::ApiKey => $this->apiKey,
            ModelSetting::OutputCheckModel => $this->outputCheckModel,
            ModelSetting::RiskModel => $this->riskModel,
        };
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $values = [];
        foreach (ModelSetting::cases() as $setting) {
            $values[$setting->value] = $this->get($setting);
        }

        return $values;
    }
}
