<?php

declare(strict_types=1);

namespace App\Connect;

/**
 * Takes back a setting the user may have had before Airlock: puts back the original value, removes it only when the
 * original did not have it, and leaves it when the original is unknown.
 */
final class SharedValue
{
    public static function restore(ConfigPath $path, \stdClass $root, ?\stdClass $original): void
    {
        if (!$original instanceof \stdClass) {
            return;
        }
        $parent = $path->existingParent($root);
        if (!$parent instanceof \stdClass) {
            return;
        }

        $key = $path->key();
        $originalParent = $path->existingParent($original);
        if ($originalParent instanceof \stdClass && property_exists($originalParent, $key)) {
            $parent->{$key} = $originalParent->{$key};

            return;
        }
        unset($parent->{$key});
    }
}
