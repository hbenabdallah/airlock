<?php

declare(strict_types=1);

namespace App\Policy;

/**
 * What the policy makes of a whole command line: its worst part decides.
 */
final readonly class Assessment
{
    /**
     * @param list<string> $reasons why it is not plain, one per concern, naming the part concerned
     */
    public function __construct(
        public Verdict $verdict,
        public array $reasons = [],
    ) {
    }
}
