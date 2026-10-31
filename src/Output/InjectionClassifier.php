<?php

declare(strict_types=1);

namespace App\Output;

interface InjectionClassifier
{
    /**
     * Never throws: when the output cannot be checked, the check says so and why.
     */
    public function check(string $output): OutputCheck;
}
