<?php

declare(strict_types=1);

namespace App\Tests\Output;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * NVIDIA's API as the server sees it in the test environment: every request gets a recorded answer, chosen by the
 * structured output it asks for, and nothing reaches the network.
 */
final class RecordedNvidiaApi
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
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
