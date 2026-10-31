<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Policy\Verdict;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\ElicitResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;

/**
 * Asks with a signed state, and takes an answer only with it: the client carries the state back on its retry, so it
 * is attacker-controlled. The state names the exact command it was minted for and is good for one answer.
 */
final class Approvals
{
    private const string APPROVAL = 'approval';

    /** @var array<string, true> the states already answered, by their one-time token */
    private array $usedTokens = [];

    public function ask(RequestContext $context, string $command, ElicitRequest $form): InputRequiredResult
    {
        return new InputRequiredResult(
            [self::APPROVAL => $form],
            $context->mintRequestState(['command' => $this->fingerprint($command), 'token' => bin2hex(random_bytes(16))]),
        );
    }

    /**
     * The user's answer to the form asked for this very command, once; null when there is none to take, and the user
     * must be asked: a first call, a forged or replayed answer, or one minted for another command.
     */
    public function answer(RequestContext $context, string $command, Verdict $verdict): ?Decision
    {
        $input = $context->getInputContext();
        $result = $input?->elicitResult(self::APPROVAL);
        $state = $input?->requestState() ?? [];
        $token = $state['token'] ?? null;
        if (!$result instanceof ElicitResult || !\is_string($token) || ($state['command'] ?? null) !== $this->fingerprint($command) || isset($this->usedTokens[$token])) {
            return null;
        }
        $this->usedTokens[$token] = true;

        if (ElicitAction::Accept !== $result->action) {
            return Decision::Refuse;
        }
        $value = $result->content[ApprovalForm::DECISION] ?? null;
        $decision = \is_string($value) ? Decision::tryFrom($value) : null;

        // An answer that was not offered (always, for a command asked every time) is no approval.
        return null !== $decision && \in_array($decision, Decision::offeredFor($verdict), true) ? $decision : Decision::Refuse;
    }

    private function fingerprint(string $command): string
    {
        return hash('sha256', $command);
    }
}
