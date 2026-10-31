<?php

declare(strict_types=1);

namespace App\Tests\Output;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The model API as the server sees it in the test environment: every request to the configured API gets a recorded
 * answer, chosen by the structured output it asks for, and nothing reaches the network.
 */
final readonly class RecordedModelApi
{
    public function __construct(
        #[Autowire(env: 'model_setting:AIRLOCK_AI_BASE_URL')]
        private string $baseUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        if ($url !== $this->baseUrl.'/v1/chat/completions') {
            return new MockResponse('{"error":{"message":"Not the configured model API."}}', ['http_code' => 404]);
        }
        $request = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '', true);
        $schema = RecordedResponses::at($request, 'response_format', 'json_schema', 'name');
        $content = RecordedResponses::at($request, 'messages', 1, 'content');
        $content = \is_string($content) ? $content : '';

        return match ($schema) {
            'InjectionVerdict' => RecordedResponses::fixture(1 === preg_match('/AI (coding )?(assistants|agents)|ignore (all )?previous instructions/i', $content) ? 'output-check-injection' : 'output-check-clean'),
            'PlainWords' => RecordedResponses::fixture('plain-words'),
            default => new MockResponse('{"error":{"message":"No recorded answer for this request."}}', ['http_code' => 400]),
        };
    }
}
