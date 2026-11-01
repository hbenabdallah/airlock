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
     * @param \stdClass|null $original the file as it was before Airlock first changed it, null when unknown
     *
     * @throws UnsupportedConfigException
     */
    public function disconnect(\stdClass $root, ?\stdClass $original): void;

    /**
     * Whether the setting may be the user's too, such as a denied tool: taken back only as far as the original says.
     */
    public function isShared(): bool;

    public function describe(Direction $direction): string;
}
