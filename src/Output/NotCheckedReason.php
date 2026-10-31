<?php

declare(strict_types=1);

namespace App\Output;

enum NotCheckedReason: string
{
    case NoApiKey = 'no_api_key';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case Error = 'error';

    public function describe(): string
    {
        return match ($this) {
            self::NoApiKey => 'no API key is configured',
            self::Timeout => 'the model did not answer in time',
            self::RateLimited => "the model's rate limit was reached",
            self::Error => 'the model could not be reached or gave no usable answer',
        };
    }
}
