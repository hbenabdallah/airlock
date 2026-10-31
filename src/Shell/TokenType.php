<?php

declare(strict_types=1);

namespace App\Shell;

enum TokenType
{
    case Word;
    /** `;`, `&`, `&&`, `||`, a newline or a parenthesis: the next command starts afresh. */
    case Separator;
    /** `|` or `|&`: the next command reads this one's output. */
    case Pipe;
    case Redirection;
}
