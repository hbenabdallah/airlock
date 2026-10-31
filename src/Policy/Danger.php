<?php

declare(strict_types=1);

namespace App\Policy;

/**
 * What makes one part of a command line asked about every time.
 */
enum Danger: string
{
    case RunsAsAnotherUser = 'runs_as_another_user';
    case DeletesRecursively = 'deletes_recursively';
    case DestroysData = 'destroys_data';
    case ActsOnWhatItFinds = 'acts_on_what_it_finds';
    case PushesToRemote = 'pushes_to_remote';
    case ConnectsToAnotherMachine = 'connects_to_another_machine';
    case RunsAGitShellAlias = 'runs_a_git_shell_alias';
    case RunsADownload = 'runs_a_download';
    case RunsWhatIsPiped = 'runs_what_is_piped';
    case RunsAString = 'runs_a_string';

    public function reason(): string
    {
        return match ($this) {
            self::RunsAsAnotherUser => 'it runs as another user',
            self::DeletesRecursively => 'it deletes recursively',
            self::DestroysData => 'it can destroy data',
            self::ActsOnWhatItFinds => 'it deletes or runs commands on what it finds',
            self::PushesToRemote => 'it pushes to a remote',
            self::ConnectsToAnotherMachine => 'it connects to another machine over SSH',
            self::RunsAGitShellAlias => 'it runs a git alias that is a shell command',
            self::RunsADownload => 'it pipes a download into a program that runs it',
            self::RunsWhatIsPiped => 'it runs what is piped into it',
            self::RunsAString => 'it runs a string as a command',
        };
    }
}
