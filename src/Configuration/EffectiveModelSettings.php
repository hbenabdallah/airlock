<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The model settings the server runs with: each `AIRLOCK_AI_*` environment variable that is set, else what
 * `models.yaml` holds.
 */
final readonly class EffectiveModelSettings
{
    public function __construct(
        #[Autowire(env: 'model_setting:AIRLOCK_AI_BASE_URL')]
        private string $baseUrl,
        #[Autowire(env: 'model_setting:AIRLOCK_AI_API_KEY')]
        #[\SensitiveParameter]
        private string $apiKey,
        #[Autowire(env: 'model_setting:AIRLOCK_OUTPUT_CHECK_MODEL')]
        private string $outputCheckModel,
        #[Autowire(env: 'model_setting:AIRLOCK_RISK_MODEL')]
        private string $riskModel,
    ) {
    }

    public function settings(): ModelSettings
    {
        return new ModelSettings($this->baseUrl, $this->apiKey, $this->outputCheckModel, $this->riskModel);
    }
}
