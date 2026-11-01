<?php

declare(strict_types=1);

namespace App\Connect;

enum Scope: string
{
    case Project = 'project';
    case User = 'user';
}
