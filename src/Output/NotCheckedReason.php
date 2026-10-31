<?php

declare(strict_types=1);

namespace App\Output;

enum NotCheckedReason: string
{
    case NotConfigured = 'not_configured';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case Error = 'error';

    public function describe(): string
    {
        return match ($this) {
            self::NotConfigured => 'no model API is configured; set one up with `airlock configure`, in a terminal',
            self::Timeout => 'the model did not answer in time',
            self::RateLimited => "the model's rate limit was reached",
            self::Error => 'the model could not be reached or gave no usable answer',
        };
    }
}
