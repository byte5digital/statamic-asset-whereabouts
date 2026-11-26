<?php

declare(strict_types=1);

namespace Byte5\Whereabouts;

/**
 * What a site decides in config/whereabouts.php.
 *
 * `containers` is null - every container, with the defaults - or a list in which a container
 * is either named ('images') or named with its own options ('files' => ['branches' => 'collapsed']).
 */
final class Settings
{
    /** Whether the addon acts on the container: every one unless the config lists some. */
    public static function includes(string $containerHandle): bool
    {
        return self::containers() === null || array_key_exists($containerHandle, self::containers());
    }

    /** Whether the branches of the container's "Used in" tree start folded; open unless it asks for 'collapsed'. */
    public static function collapsed(string $containerHandle): bool
    {
        return (self::containers()[$containerHandle]['branches'] ?? 'open') === 'collapsed';
    }

    /**
     * Who may delete the container's assets that are in use: 'warn' - everybody who may delete
     * assets, after the warning (the default) -, 'permission' - only users with the
     * "delete assets in use" permission -, or 'block' - nobody.
     */
    public static function deletion(string $containerHandle): string
    {
        $mode = self::containers()[$containerHandle]['delete'] ?? 'warn';

        return in_array($mode, ['permission', 'block'], true) ? $mode : 'warn';
    }

    /**
     * The listed containers with their options, by handle; null where the config lists none.
     *
     * @return array<string, array<array-key, mixed>>|null
     */
    private static function containers(): ?array
    {
        $configured = config('whereabouts.containers');

        if (! is_array($configured)) {
            return null;
        }

        $containers = [];

        foreach ($configured as $key => $value) {
            if (is_string($key)) {
                $containers[$key] = is_array($value) ? $value : [];
            } elseif (is_string($value)) {
                $containers[$value] = [];
            }
        }

        return $containers;
    }
}
