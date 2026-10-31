<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Airlock started as a client starts it, `bin/console mcp:server`, spoken to one JSON-RPC line at a time.
 */
final class StdioClient
{
    private const float ANSWER_TIMEOUT_SECONDS = 30.0;

    private readonly InputStream $input;

    private readonly Process $process;

    private string $buffer = '';

    /**
     * @param array<string, string> $environment
     */
    public function __construct(string $projectDirectory, array $environment = [])
    {
        $this->input = new InputStream();
        $this->process = new Process(
            [\PHP_BINARY, __DIR__.'/../../bin/console', 'mcp:server'],
            cwd: $projectDirectory,
            env: ['APP_ENV' => 'test', ...$environment],
            input: $this->input,
            timeout: null,
        );
        $this->process->start();
    }

    /**
     * @param array<string, mixed> $message
     */
    public function send(array $message): void
    {
        $this->input->write(json_encode($message, \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * The next message the server writes.
     *
     * @return array<mixed>
     */
    public function receive(): array
    {
        $deadline = microtime(true) + self::ANSWER_TIMEOUT_SECONDS;
        while (!str_contains($this->buffer, "\n")) {
            if (microtime(true) > $deadline || !$this->process->isRunning()) {
                throw new \RuntimeException(\sprintf("No answer from the server.\nIts error output:\n%s", $this->process->getErrorOutput()));
            }
            $this->buffer .= $this->process->getIncrementalOutput();
            usleep(10_000);
        }

        [$line, $this->buffer] = explode("\n", $this->buffer, 2);
        $message = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($message)) {
            throw new \RuntimeException(\sprintf('The server wrote a line that is no JSON-RPC message: %s', $line));
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<mixed>
     */
    public function request(array $message): array
    {
        $this->send($message);

        return $this->receive();
    }

    public function stop(): void
    {
        $this->input->close();
        $this->process->wait();
    }
}
