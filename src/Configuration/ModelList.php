<?php

declare(strict_types=1);

namespace App\Configuration;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The models a chat completions API says it serves, from GET /v1/models: what it lists, not what works.
 */
final readonly class ModelList
{
    public function __construct(
        #[Target('models.client')]
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<string>
     *
     * @throws \RuntimeException when the API does not answer with a list of models
     */
    public function of(string $baseUrl, #[\SensitiveParameter] string $apiKey): array
    {
        try {
            $answer = $this->httpClient->request('GET', rtrim($baseUrl, '/').'/v1/models', ['auth_bearer' => $apiKey])->toArray();
        } catch (HttpClientException $e) {
            throw new \RuntimeException(\sprintf('%s/v1/models did not answer: %s', rtrim($baseUrl, '/'), $e->getMessage()), $e->getCode(), previous: $e);
        }

        $models = [];
        foreach (\is_array($answer['data'] ?? null) ? $answer['data'] : [] as $model) {
            $id = \is_array($model) ? $model['id'] ?? null : null;
            if (\is_string($id) && '' !== $id) {
                $models[] = $id;
            }
        }

        return $models;
    }
}
