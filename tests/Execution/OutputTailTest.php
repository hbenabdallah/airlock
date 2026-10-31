<?php

declare(strict_types=1);

namespace App\Tests\Execution;

use App\Execution\OutputTail;
use PHPUnit\Framework\TestCase;

final class OutputTailTest extends TestCase
{
    public function testAShortOutputIsKeptWhole(): void
    {
        $tail = new OutputTail(10);
        $tail->append('abc');
        $tail->append('def');

        self::assertSame('abcdef', $tail->text());
        self::assertSame(0, $tail->droppedBytes());
    }

    public function testAnOutputWithoutEndKeepsItsEndAndCountsWhatItDropped(): void
    {
        $tail = new OutputTail(10);
        foreach (range(1, 100) as $line) {
            $tail->append(\sprintf("%03d\n", $line));
        }

        self::assertLessThanOrEqual(20, \strlen($tail->text()));
        self::assertStringEndsWith("099\n100\n", $tail->text());
        self::assertSame(400, $tail->droppedBytes() + \strlen($tail->text()));
    }
}
