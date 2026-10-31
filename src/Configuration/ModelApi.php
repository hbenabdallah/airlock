<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The chat completions API the model checks go through: off until both its URL and its key are set.
 */
final readonly class ModelApi
{
    public function __construct(
        #[Autowire(env: 'AIRLOCK_AI_BASE_URL')]
        private string $baseUrl,
        #[Autowire(env: 'AIRLOCK_AI_API_KEY')]
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->baseUrl && '' !== $this->apiKey;
    }
}
