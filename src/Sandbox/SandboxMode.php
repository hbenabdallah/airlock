<?php

declare(strict_types=1);

namespace App\Sandbox;

enum SandboxMode: string
{
    /** Writes kept to the project, /tmp and package caches; Airlock's files and the user's keys hidden. */
    case Auto = 'auto';
    /** As auto, with no network either. */
    case Strict = 'strict';
    /** No sandbox: every command is asked about. */
    case Off = 'off';
}
