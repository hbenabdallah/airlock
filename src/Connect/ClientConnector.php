<?php

declare(strict_types=1);

namespace App\Connect;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What connecting Airlock to one client takes: its server entry, and its own shell tool turned off where the client
 * lets a file do it.
 */
#[AutoconfigureTag]
interface ClientConnector
{
    public function client(): Client;

    /**
     * @return non-empty-list<Scope> the first is the default
     */
    public function scopes(): array;

    /**
     * @return non-empty-list<FileEdit>
     */
    public function edits(Scope $scope, ConnectionContext $context): array;

    /**
     * What the user should know or do by hand, such as turning off a shell tool no file controls.
     *
     * @return list<string>
     */
    public function notes(Scope $scope): array;
}
