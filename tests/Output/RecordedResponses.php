<?php

declare(strict_types=1);

namespace App\Tests\Output;

use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers recorded once from a real model API (NVIDIA's), served by a mock client to a real Symfony AI platform.
 */
final class RecordedResponses
{
    public const string DIRECTORY = __DIR__.'/../Fixtures/nvidia';

    public static function platform(MockHttpClient $httpClient): PlatformInterface
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());

        return Factory::createPlatform('https://integrate.api.nvidia.com', 'test-key', $httpClient, eventDispatcher: $dispatcher, supportsEmbeddings: false, name: 'models');
    }

    public static function fixture(string $name, int $status = 200): MockResponse
    {
        $body = file_get_contents(self::DIRECTORY.'/'.$name.'.json');
        if (false === $body) {
            throw new \RuntimeException(\sprintf('No recorded response named "%s".', $name));
        }

        return new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * The value at a path in decoded JSON, or null when the path is not there.
     */
    public static function at(mixed $value, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * A recorded completion whose message the model wrote is replaced: for answers the real model did not give.
     */
    public static function answering(string $content): MockResponse
    {
        $body = json_decode((string) file_get_contents(self::DIRECTORY.'/plain-words.json'), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($body) || !\is_array($body['choices'] ?? null) || !\is_array($body['choices'][0] ?? null) || !\is_array($body['choices'][0]['message'] ?? null)) {
            throw new \RuntimeException('The recorded completion has no message.');
        }
        $body['choices'][0]['message']['content'] = $content;

        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
