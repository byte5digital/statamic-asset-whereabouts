<?php

declare(strict_types=1);

namespace Byte5\Whereabouts;

/** What a site decides in config/whereabouts.php. */
final class Settings
{
    /** Whether the addon acts on the container: every one unless the config lists some. */
    public static function includes(string $containerHandle): bool
    {
        $containers = config('whereabouts.containers');

        return ! is_array($containers) || in_array($containerHandle, $containers, true);
    }
}
