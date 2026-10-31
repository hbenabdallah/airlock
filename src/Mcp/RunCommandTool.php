<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Execution\CommandResult;
use App\Execution\CommandRunner;
use App\Policy\Assessment;
use App\Policy\CommandPolicy;
use App\Policy\Verdict;
use App\Sandbox\SandboxDetector;
use App\Shell\ShellParser;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Elicitation\EnumSchemaDefinition;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\ElicitResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;

/**
 * Runs a command only once the user has approved it in their client, and only in the sandbox.
 *
 * Written for the 2026-07-28 protocol: the first call returns the form as an input-required result, and the client's
 * retry carries the answer. The SDK serves the same tool to 2025-11-25 clients through elicitation/create.
 */
#[McpTool(
    name: 'run_command',
    description: 'Run a shell command (bash) in the project, in a sandbox, after the user approves it. Returns its exit code and output.',
)]
final readonly class RunCommandTool
{
    private const string APPROVAL = 'approval';

    private const string DECISION = 'decision';

    public function __construct(
        private ShellParser $parser,
        private CommandPolicy $policy,
        private SandboxDetector $sandboxDetector,
        private CommandRunner $runner,
    ) {
    }

    /**
     * @param string $command the command line, as typed in bash
     */
    public function __invoke(string $command, RequestContext $context): CallToolResult|InputRequiredResult
    {
        if ('' === trim($command)) {
            return CallToolResult::error([new TextContent('The command is empty: there is nothing to run.')]);
        }

        $assessment = $this->policy->assess($this->parser->parse($command));
        if (Verdict::Refused === $assessment->verdict) {
            return CallToolResult::error([new TextContent(\sprintf(
                "Refused by Airlock's policy, without asking the user: %s did not run.\n%s",
                $command,
                implode("\n", $assessment->reasons),
            ))]);
        }

        $answer = $context->getInputContext()?->elicitResult(self::APPROVAL);
        if (!$answer instanceof ElicitResult) {
            return new InputRequiredResult([self::APPROVAL => $this->approvalRequest($command, $assessment)]);
        }
        if (!$this->isApprovedOnce($answer)) {
            return CallToolResult::error([new TextContent(\sprintf('Refused by the user: %s did not run.', $command))]);
        }

        return $this->toolResult($this->runner->run($command));
    }

    private function approvalRequest(string $command, Assessment $assessment): ElicitRequest
    {
        $decisions = Decision::cases();

        return new ElicitRequest(
            implode("\n\n", [
                "Run this command?\n\n    ".$command,
                $this->whyAsked($assessment),
                $this->sandboxDetector->detect()->describe(),
            ]),
            new ElicitationSchema(
                [self::DECISION => new EnumSchemaDefinition(
                    title: 'Decision',
                    enum: array_map(static fn (Decision $decision): string => $decision->value, $decisions),
                    enumNames: array_map(static fn (Decision $decision): string => $decision->label(), $decisions),
                )],
                [self::DECISION],
            ),
        );
    }

    private function whyAsked(Assessment $assessment): string
    {
        if (Verdict::AskEveryTime === $assessment->verdict) {
            return "Asked every time, because:\n- ".implode("\n- ", $assessment->reasons);
        }

        return 'Asked because no grant covers it.';
    }

    private function isApprovedOnce(ElicitResult $answer): bool
    {
        return ElicitAction::Accept === $answer->action
            && Decision::Once->value === ($answer->content[self::DECISION] ?? null);
    }

    private function toolResult(CommandResult $result): CallToolResult
    {
        if ($result->hasTimedOut) {
            return CallToolResult::error([new TextContent("Stopped: the command ran past its time limit.\n\n".$result->output)]);
        }

        $text = \sprintf("Exit code %d\n\n%s", $result->exitCode ?? -1, $result->output);
        $content = [new TextContent($text)];

        return $result->isSuccessful() ? CallToolResult::success($content) : CallToolResult::error($content);
    }
}
