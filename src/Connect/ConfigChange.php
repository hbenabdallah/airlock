<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * One setting Airlock adds to a client's configuration when it connects, and takes back when it disconnects.
 */
interface ConfigChange
{
    public function path(): ConfigPath;

    /**
     * @throws UnsupportedConfigException
     */
    public function connect(\stdClass $root): void;

    /**
     * @throws UnsupportedConfigException
     */
    public function disconnect(\stdClass $root): void;

    public function describe(Direction $direction): string;
}
