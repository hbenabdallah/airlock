<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * A client's configuration file that Airlock cannot edit without risking it: the user makes the change by hand.
 */
final class UnsupportedConfigException extends \RuntimeException
{
}
