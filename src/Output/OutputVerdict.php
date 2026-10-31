<?php

declare(strict_types=1);

namespace App\Output;

enum OutputVerdict: string
{
    case Clean = 'clean';
    case Injection = 'injection';
    case NotChecked = 'not_checked';
}
