<?php

declare(strict_types=1);

namespace App\Output;

/**
 * Splits an output into chunks a model reads well, overlapping so that an injection across two chunks is whole in
 * one of them. Past the chunks it may read, it keeps the end of the output, where results and errors are.
 */
final readonly class OutputChunker
{
    public function __construct(
        private int $chunkBytes = 16 * 1024,
        private int $overlapBytes = 1024,
        private int $maxChunks = 5,
    ) {
    }

    public function split(string $text): Chunks
    {
        $length = \strlen($text);
        if ($length <= $this->chunkBytes) {
            return new Chunks([$text]);
        }

        $step = $this->chunkBytes - $this->overlapBytes;
        $readable = $this->chunkBytes + ($this->maxChunks - 1) * $step;
        $unchecked = max(0, $length - $readable);
        $text = mb_strcut($text, $unchecked);
        $length = \strlen($text);

        $chunks = [];
        for ($start = 0;; $start += $step) {
            $chunks[] = mb_strcut($text, $start, $this->chunkBytes);
            if ($start + $this->chunkBytes >= $length) {
                break;
            }
        }

        return new Chunks($chunks, $unchecked);
    }
}
