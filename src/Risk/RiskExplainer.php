<?php

declare(strict_types=1);

namespace App\Risk;

interface RiskExplainer
{
    /**
     * One short line saying what the command would do, or null when there is none to trust: never throws.
     */
    public function explain(string $command): ?string;
}
