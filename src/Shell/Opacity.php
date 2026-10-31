<?php

declare(strict_types=1);

namespace App\Shell;

/**
 * Why a command line cannot be read whole: what it runs is decided when it runs, not by its words.
 */
enum Opacity: string
{
    case CommandSubstitution = 'command_substitution';
    case ProcessSubstitution = 'process_substitution';
    case HereDocument = 'here_document';
    case CompoundCommand = 'compound_command';
    case ComputedProgram = 'computed_program';
    case UnclosedQuote = 'unclosed_quote';
    case MissingRedirectionTarget = 'missing_redirection_target';

    public function reason(): string
    {
        return match ($this) {
            self::CommandSubstitution => 'part of it is the output of another command',
            self::ProcessSubstitution => 'it reads or writes through another command',
            self::HereDocument => 'it holds a here-document',
            self::CompoundCommand => 'it is a loop or a condition, which decides at run time what runs',
            self::ComputedProgram => 'the program it runs is computed at run time',
            self::UnclosedQuote => 'a quote is never closed',
            self::MissingRedirectionTarget => 'a redirection has no target',
        };
    }
}
