<?php

declare(strict_types=1);

namespace App\Policy;

enum Verdict: string
{
    /** Nothing in it is dangerous: a grant could cover it. */
    case Plain = 'plain';
    /** Dangerous, or unreadable: the user is asked each time, and no grant covers it. */
    case AskEveryTime = 'ask_every_time';
    /** Forbidden by the policy: it never runs, and the user is not asked. */
    case Refused = 'refused';

    public function isWorseThan(self $other): bool
    {
        return $this->severity() > $other->severity();
    }

    private function severity(): int
    {
        return match ($this) {
            self::Plain => 0,
            self::AskEveryTime => 1,
            self::Refused => 2,
        };
    }
}
