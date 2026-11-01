<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A client file changed between the time Airlock read it and the time it was about to write it: writing the planned
 * content would undo that change.
 */
final class ConfigChangedException extends \RuntimeException
{
}
