<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The server end to end over stdio, as a client sees it, in both protocol eras: 2026-07-28, where the tool returns
 * an input-required result and the client calls again with the answer; and 2025-11-25, where the server sends
 * elicitation/create in the middle of the call.
 */
#[RequiresOperatingSystemFamily('Linux')]
final class RunCommandOverStdioTest extends TestCase
{
    private const string PROJECT = __DIR__.'/../../var/test-stdio/project';

    private const string COMMAND = 'echo approved > made.txt && cat made.txt';

    private ?StdioClient $client = null;

    #[\Override]
    protected function setUp(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(self::PROJECT);
        $filesystem->mkdir(self::PROJECT);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->client?->stop();
        new Filesystem()->remove(\dirname(self::PROJECT));
    }

    public function testInTheModernEraTheFirstCallAsksWithAForm(): void
    {
        $answer = $this->client()->request($this->modernCall(1, 'git push'));

        self::assertSame('input_required', $this->field($answer, 'result', 'resultType'));
        self::assertSame('elicitation/create', $this->field($answer, 'result', 'inputRequests', 'approval', 'method'));
        $form = $this->field($answer, 'result', 'inputRequests', 'approval', 'params');
        self::assertStringContainsString('git push', $this->text($form, 'message'));
        self::assertStringContainsString('it pushes to a remote', $this->text($form, 'message'));
        self::assertSame(['once', 'refuse'], $this->field($form, 'requestedSchema', 'properties', 'decision', 'enum'));
    }

    public function testInTheModernEraAnApprovedRetryRunsTheCommand(): void
    {
        $this->client()->request($this->modernCall(1, self::COMMAND));

        $answer = $this->client()->request($this->modernCall(2, self::COMMAND, ['action' => 'accept', 'content' => ['decision' => 'once']]));

        self::assertFalse($this->field($answer, 'result', 'isError'));
        self::assertSame("Exit code 0\n\napproved\n", $this->field($answer, 'result', 'content', 0, 'text'));
        self::assertFileExists(self::PROJECT.'/made.txt');
    }

    public function testInTheModernEraARefusedRetryRunsNothing(): void
    {
        $this->client()->request($this->modernCall(1, self::COMMAND));

        $answer = $this->client()->request($this->modernCall(2, self::COMMAND, ['action' => 'accept', 'content' => ['decision' => 'refuse']]));

        self::assertTrue($this->field($answer, 'result', 'isError'));
        self::assertStringContainsString('Refused by the user', $this->text($answer, 'result', 'content', 0, 'text'));
        self::assertFileDoesNotExist(self::PROJECT.'/made.txt');
    }

    public function testInTheModernEraADeclinedFormRunsNothing(): void
    {
        $answer = $this->client()->request($this->modernCall(1, self::COMMAND, ['action' => 'decline']));

        self::assertTrue($this->field($answer, 'result', 'isError'));
        self::assertFileDoesNotExist(self::PROJECT.'/made.txt');
    }

    public function testInTheModernEraAnApprovalDoesNotCarryOverToTheNextCall(): void
    {
        $this->client()->request($this->modernCall(1, self::COMMAND, ['action' => 'accept', 'content' => ['decision' => 'once']]));

        $answer = $this->client()->request($this->modernCall(2, 'touch other.txt'));

        self::assertSame('input_required', $this->field($answer, 'result', 'resultType'));
        self::assertFileDoesNotExist(self::PROJECT.'/other.txt');
    }

    public function testInTheHandshakeEraTheServerAsksThroughElicitationAndRunsWhenApproved(): void
    {
        $this->initializeHandshake();

        $ask = $this->client()->request($this->handshakeCall(2, self::COMMAND));
        self::assertSame('elicitation/create', $this->field($ask, 'method'));
        self::assertStringContainsString(self::COMMAND, $this->text($ask, 'params', 'message'));

        $answer = $this->client()->request(['jsonrpc' => '2.0', 'id' => $this->field($ask, 'id'), 'result' => ['action' => 'accept', 'content' => ['decision' => 'once']]]);

        self::assertSame(2, $this->field($answer, 'id'));
        self::assertSame("Exit code 0\n\napproved\n", $this->field($answer, 'result', 'content', 0, 'text'));
        self::assertFileExists(self::PROJECT.'/made.txt');
    }

    public function testInTheHandshakeEraARefusedAnswerRunsNothing(): void
    {
        $this->initializeHandshake();

        $ask = $this->client()->request($this->handshakeCall(2, self::COMMAND));
        $answer = $this->client()->request(['jsonrpc' => '2.0', 'id' => $this->field($ask, 'id'), 'result' => ['action' => 'accept', 'content' => ['decision' => 'refuse']]]);

        self::assertTrue($this->field($answer, 'result', 'isError'));
        self::assertStringContainsString('Refused by the user', $this->text($answer, 'result', 'content', 0, 'text'));
        self::assertFileDoesNotExist(self::PROJECT.'/made.txt');
    }

    public function testInTheHandshakeEraTheToolIsListed(): void
    {
        $this->initializeHandshake();

        $answer = $this->client()->request(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        self::assertSame('run_command', $this->field($answer, 'result', 'tools', 0, 'name'));
    }

    public function testACommandThePolicyRefusesIsRefusedWithoutAsking(): void
    {
        $this->client = new StdioClient(self::PROJECT, ['AIRLOCK_REFUSE' => '["terraform destroy"]']);

        $answer = $this->client->request($this->modernCall(1, 'make test && terraform destroy'));

        self::assertTrue($this->field($answer, 'result', 'isError'));
        self::assertStringContainsString("Refused by Airlock's policy", $this->text($answer, 'result', 'content', 0, 'text'));
    }

    private function client(): StdioClient
    {
        return $this->client ??= new StdioClient(self::PROJECT);
    }

    private function initializeHandshake(): void
    {
        $answer = $this->client()->request([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => ['elicitation' => ['form' => new \stdClass()]],
                'clientInfo' => ['name' => 'test', 'version' => '1'],
            ],
        ]);
        self::assertSame('2025-11-25', $this->field($answer, 'result', 'protocolVersion'));
        $this->client()->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
    }

    /**
     * @return array<string, mixed>
     */
    private function handshakeCall(int $id, string $command): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => 'run_command', 'arguments' => ['command' => $command]]];
    }

    /**
     * @param array<string, mixed>|null $approval the answer to the form, carried by a retry
     *
     * @return array<string, mixed>
     */
    private function modernCall(int $id, string $command, ?array $approval = null): array
    {
        $params = [
            'name' => 'run_command',
            'arguments' => ['command' => $command],
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => ['elicitation' => ['form' => new \stdClass()]],
            ],
        ];
        if (null !== $approval) {
            $params['inputResponses'] = ['approval' => $approval];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => $params];
    }

    /**
     * The value at a path in a decoded message, failing the test when the path is not there.
     */
    private function field(mixed $message, string|int ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($message) || !\array_key_exists($key, $message)) {
                self::fail(\sprintf('The message has no "%s": %s', $key, json_encode($message)));
            }
            $message = $message[$key];
        }

        return $message;
    }

    private function text(mixed $message, string|int ...$path): string
    {
        $value = $this->field($message, ...$path);
        self::assertIsString($value);

        return $value;
    }
}
