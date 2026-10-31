<?php

declare(strict_types=1);

namespace App\Shell;

/**
 * A command line as the shell reads it: the simple commands it runs, in order.
 */
final readonly class CommandLine
{
    /**
     * @param list<SimpleCommand> $commands
     * @param Opacity|null        $opacity  why it cannot be read whole, or null when its words say everything it runs
     */
    public function __construct(
        public array $commands,
        public ?Opacity $opacity = null,
    ) {
    }

    public function isOpaque(): bool
    {
        return $this->opacity instanceof Opacity;
    }
}
