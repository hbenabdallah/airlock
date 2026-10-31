<?php

declare(strict_types=1);

namespace App\Grant;

/**
 * Grants for as long as this Airlock runs: a client starts its own Airlock, so this is its session.
 */
final class SessionGrants
{
    /** @var list<Grant> */
    private array $grants = [];

    /**
     * @param list<Grant> $grants
     */
    public function add(array $grants): void
    {
        foreach ($grants as $grant) {
            if (!array_any($this->grants, $grant->equals(...))) {
                $this->grants[] = $grant;
            }
        }
    }

    /**
     * @return list<Grant>
     */
    public function all(): array
    {
        return $this->grants;
    }
}
