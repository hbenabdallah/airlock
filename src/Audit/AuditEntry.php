<?php

declare(strict_types=1);

namespace App\Audit;

use App\Policy\Verdict;

final readonly class AuditEntry
{
    /**
     * @param list<string> $reasons  why the policy did not find it plain
     * @param int|null     $exitCode null when it did not run, or was stopped
     */
    public function __construct(
        public \DateTimeImmutable $at,
        public string $project,
        public string $command,
        public Verdict $verdict,
        public Outcome $outcome,
        public array $reasons = [],
        public ?int $exitCode = null,
        public bool $hasTimedOut = false,
        public ?string $detail = null,
    ) {
    }

    /**
     * @return array<string, scalar|list<string>|null>
     */
    public function toArray(): array
    {
        return [
            'at' => $this->at->format(\DATE_ATOM),
            'project' => $this->project,
            'command' => $this->command,
            'verdict' => $this->verdict->value,
            'outcome' => $this->outcome->value,
            'reasons' => $this->reasons,
            'exit_code' => $this->exitCode,
            'timed_out' => $this->hasTimedOut,
            'detail' => $this->detail,
        ];
    }
}
