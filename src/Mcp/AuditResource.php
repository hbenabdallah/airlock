<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Audit\AuditLog;
use App\Execution\ProjectDirectory;
use Mcp\Capability\Attribute\McpResource;

#[McpResource(
    uri: 'airlock://audit',
    name: 'audit',
    description: 'The last decisions Airlock made about commands in this project, oldest first, one JSON object per line.',
    mimeType: 'application/x-ndjson',
)]
final readonly class AuditResource
{
    private const int ENTRIES = 200;

    public function __construct(
        private AuditLog $auditLog,
        private ProjectDirectory $projectDirectory,
    ) {
    }

    public function __invoke(): string
    {
        return implode("\n", $this->auditLog->lastEntriesFor($this->projectDirectory->path, self::ENTRIES));
    }
}
