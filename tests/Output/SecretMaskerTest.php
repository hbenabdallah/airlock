<?php

declare(strict_types=1);

namespace App\Tests\Output;

use App\Output\SecretKind;
use App\Output\SecretMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretMaskerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function secrets(): iterable
    {
        yield 'AWS access key' => ['aws_access_key_id = AKIAIOSFODNN7EXAMPLE', 'aws_access_key_id = [masked: aws-access-key]'];
        yield 'AWS temporary access key' => ['key ASIAY34FZKBOKMUTVV7A in use', 'key [masked: aws-access-key] in use'];
        yield 'GitHub personal token' => ['remote: ghp_16C7e42F292c6912E7710c838347Ae178B4a ok', 'remote: [masked: github-token] ok'];
        yield 'GitHub OAuth token' => ['gho_16C7e42F292c6912E7710c838347Ae178B4a', '[masked: github-token]'];
        yield 'GitHub user-to-server token' => ['ghu_16C7e42F292c6912E7710c838347Ae178B4a', '[masked: github-token]'];
        yield 'GitHub server-to-server token' => ['ghs_16C7e42F292c6912E7710c838347Ae178B4a', '[masked: github-token]'];
        yield 'GitHub refresh token' => ['ghr_16C7e42F292c6912E7710c838347Ae178B4a', '[masked: github-token]'];
        yield 'GitHub fine-grained token' => ['github_pat_11ABCDEFG0123456789_abcdefghijklmnopqrstuvwxyz', '[masked: github-token]'];
        yield 'OpenAI key' => ['OpenAI: sk-4f3Vb2kLmNoPqRsTuVwXyZ0123456789abcdEFGH', 'OpenAI: [masked: openai-key]'];
        yield 'OpenAI project key' => ['sk-proj-Ab3dEfGh1jKlMnOpQrStUvWxYz0123456789_-abc', '[masked: openai-key]'];
        yield 'Anthropic key' => ['sk-ant-api03-Ab3dEfGh1jKlMnOpQrStUvWxYz0123456789', '[masked: anthropic-key]'];
        yield 'Hugging Face token' => ['hf_aBcDeFgHiJkLmNoPqRsTuVwXyZ01234567', '[masked: hugging-face-token]'];
        yield 'NVIDIA key' => ['nvapi-Ab3dEfGh1jKlMnOpQrStUvWxYz0123456789_-AbCdEfGh', '[masked: nvidia-key]'];
        yield 'Slack bot token' => ['xoxb-123456789012-1234567890123-AbCdEfGhIjKlMnOpQrStUvWx', '[masked: slack-token]'];
        yield 'Slack user token' => ['xoxp-123456789012-1234567890', '[masked: slack-token]'];
        yield 'Google API key' => ['key=AIzaSyA1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q.', 'key=[masked: google-api-key].'];
        yield 'Stripe secret key' => ['sk_live_4eC39HqLyjWDarjtT1zdp7dc', '[masked: stripe-key]'];
        yield 'Stripe restricted key' => ['rk_live_4eC39HqLyjWDarjtT1zdp7dc', '[masked: stripe-key]'];
        yield 'PEM private key' => [
            "before\n-----BEGIN RSA PRIVATE KEY-----\nMIIEpAIBAAKCAQEA0Z3VS5JJcds3xfn\n-----END RSA PRIVATE KEY-----\nafter",
            "before\n[masked: private-key]\nafter",
        ];
        yield 'OpenSSH private key' => ["-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjE=\n-----END OPENSSH PRIVATE KEY-----", '[masked: private-key]'];
        yield 'private key cut before its end' => ["-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASC", '[masked: private-key]'];
        yield 'password in a .env line' => ['DB_PASSWORD=hunter2', 'DB_PASSWORD=[masked: secret-assignment]'];
        yield 'quoted secret' => ['APP_SECRET="f75e5d2dd71b609eef5dd868aa1f7394"', 'APP_SECRET="[masked: secret-assignment]"'];
        yield 'exported token' => ['export NPM_TOKEN=npm_abc123', 'export NPM_TOKEN=[masked: secret-assignment]'];
        yield 'lowercase password with a colon' => ['  password: s3cr3t', '  password: [masked: secret-assignment]'];
        yield 'API key assignment' => ['STRIPE_API_KEY=whatever-it-is', 'STRIPE_API_KEY=[masked: secret-assignment]'];
        yield 'known token keeps its kind in an assignment' => ['GITHUB_TOKEN=ghp_16C7e42F292c6912E7710c838347Ae178B4a', 'GITHUB_TOKEN=[masked: github-token]'];
    }

    #[DataProvider('secrets')]
    public function testASecretIsMaskedWithItsKind(string $output, string $expected): void
    {
        $masked = new SecretMasker()->mask($output);

        self::assertSame($expected, $masked->text);
        self::assertSame(1, $masked->maskedSecrets);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lookAlikes(): iterable
    {
        yield 'a git SHA' => ['commit 9fceb02d0ae598e95dc970b74767f19372d61af8'];
        yield 'a UUID' => ['request 3f2504e0-4f89-11d3-9a0c-0305e82c3301 done'];
        yield '"sk-" in prose' => ['OpenAI keys start with sk- followed by letters and digits.'];
        yield 'a word after sk-' => ['pip install sk-learn-extensions-for-everyone'];
        yield 'the word token' => ['Your token expired: log in again to get a new token.'];
        yield 'the word password in prose' => ['Enter password: '];
        yield 'a counter named like a token' => ['max_tokens=60'];
        yield 'an empty assignment' => ['APP_SECRET='];
        yield 'a public key' => ['-----BEGIN PUBLIC KEY-----'];
        yield 'an AWS-like word' => ['AKIA is the prefix of an access key id.'];
        yield 'a short hf_ name' => ['hf_hub_download(repo_id)'];
    }

    #[DataProvider('lookAlikes')]
    public function testALookAlikeIsLeftAsItIs(string $output): void
    {
        $masked = new SecretMasker()->mask($output);

        self::assertSame($output, $masked->text);
        self::assertSame(0, $masked->maskedSecrets);
    }

    public function testEverySecretInAnOutputIsCounted(): void
    {
        $masked = new SecretMasker()->mask("AKIAIOSFODNN7EXAMPLE\nDB_PASSWORD=hunter2\nAKIAI44QH8DHBEXAMPLE\n");

        self::assertSame("[masked: aws-access-key]\nDB_PASSWORD=[masked: secret-assignment]\n[masked: aws-access-key]\n", $masked->text);
        self::assertSame(3, $masked->maskedSecrets);
    }

    public function testEveryKindHasATestedMask(): void
    {
        $kinds = array_map(static fn (array $case): string => $case[1], iterator_to_array(self::secrets()));

        foreach (SecretKind::cases() as $kind) {
            self::assertNotEmpty(array_filter($kinds, static fn (string $expected): bool => str_contains($expected, $kind->mask())), $kind->name);
        }
    }
}
