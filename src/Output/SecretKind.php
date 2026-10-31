<?php

declare(strict_types=1);

namespace App\Output;

/**
 * A kind of secret Airlock masks in a command's output, in the order the masks apply: a private key block first, since
 * its body can look like anything, and an assignment last, so a known token keeps its name.
 */
enum SecretKind: string
{
    case PrivateKey = 'private-key';
    case AnthropicKey = 'anthropic-key';
    case OpenAiKey = 'openai-key';
    case GitHubToken = 'github-token';
    case HuggingFaceToken = 'hugging-face-token';
    case NvidiaKey = 'nvidia-key';
    case SlackToken = 'slack-token';
    case GoogleApiKey = 'google-api-key';
    case StripeKey = 'stripe-key';
    case AwsAccessKey = 'aws-access-key';
    case Assignment = 'secret-assignment';

    public function pattern(): string
    {
        return match ($this) {
            self::PrivateKey => '/-----BEGIN[A-Z0-9 ]*PRIVATE KEY-----.*?(?:-----END[A-Z0-9 ]*PRIVATE KEY-----|\z)/s',
            self::AnthropicKey => '/\bsk-ant-[A-Za-z0-9_-]{20,}/',
            self::OpenAiKey => '/\bsk-(?!ant-)(?=[A-Za-z0-9_-]*\d)[A-Za-z0-9_-]{20,}/',
            self::GitHubToken => '/\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{22,})\b/',
            self::HuggingFaceToken => '/\bhf_[A-Za-z0-9]{30,}\b/',
            self::NvidiaKey => '/\bnvapi-[A-Za-z0-9_-]{40,}/',
            self::SlackToken => '/\bxox[abprs]-[A-Za-z0-9-]{10,}/',
            self::GoogleApiKey => '/\bAIza[0-9A-Za-z_-]{35}(?![0-9A-Za-z_-])/',
            self::StripeKey => '/\b[rs]k_live_[0-9A-Za-z]{16,}\b/',
            self::AwsAccessKey => '/\b(?:AKIA|ASIA)[A-Z0-9]{16}\b/',
            self::Assignment => '/^[ \t]*(?:export[ \t]+)?[A-Za-z0-9_.-]*(?:password|passwd|secret|token|api_?key|secret_?key|access_?key|private_?key)[ \t]*[=:][ \t]*["\']?\K(?!\[masked: )[^\s"\'#]+/im',
        };
    }

    public function mask(): string
    {
        return '[masked: '.$this->value.']';
    }
}
