<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ClientConnectors
{
    /**
     * @param iterable<ClientConnector> $connectors
     */
    public function __construct(
        #[AutowireIterator(ClientConnector::class)]
        private iterable $connectors,
    ) {
    }

    public function for(Client $client): ClientConnector
    {
        foreach ($this->connectors as $connector) {
            if ($connector->client() === $client) {
                return $connector;
            }
        }

        throw new \LogicException(\sprintf('No connector for %s.', $client->label()));
    }
}
