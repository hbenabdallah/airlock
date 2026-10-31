<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Codex: the server and the `shell_tool` feature turned off, which removes every shell tool, in its `config.toml`.
 */
final readonly class CodexConnector implements ClientConnector
{
    public function __construct(
        #[Autowire(env: 'default::CODEX_HOME')]
        private ?string $codexHome = null,
    ) {
    }

    #[\Override]
    public function client(): Client
    {
        return Client::Codex;
    }

    #[\Override]
    public function scopes(): array
    {
        return [Scope::User];
    }

    #[\Override]
    public function edits(Scope $scope, ConnectionContext $context): array
    {
        $home = null === $this->codexHome || '' === $this->codexHome ? $context->home.'/.codex' : $this->codexHome;
        $server = new ConfigTable(['command' => $context->server->command, 'args' => $context->server->arguments]);

        return [new FileEdit($home.'/config.toml', ConfigFormat::Toml, [
            new SetEntry(ConfigPath::of('mcp_servers', 'airlock'), $server),
            new SetEntry(ConfigPath::of('features', 'shell_tool'), false),
        ])];
    }

    #[\Override]
    public function notes(Scope $scope): array
    {
        return [];
    }
}
