<?php

declare(strict_types=1);

namespace App\Output;

enum InjectionLabel: string
{
    case Clean = 'clean';
    case Injection = 'injection';
}
