<?php

declare(strict_types=1);

namespace Byte5\Whereabouts;

use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\User;

/**
 * Who may delete an asset that is in use, as each container's `delete` setting says: everybody
 * who may delete assets ('warn', the default), only users with the permission below
 * ('permission'), or nobody ('block').
 *
 * It answers for the control panel's delete dialog. Code that deletes an asset - a replace that
 * drops the original, a command - is not held back.
 */
class Protection
{
    /** The permission a role needs where a container asks for one. */
    public const PERMISSION = 'delete assets in use';

    public function __construct(private readonly UsageMap $map) {}

    /**
     * The assets of a selection the current user may not delete: those in use in a container
     * that holds this user back.
     *
     * @param  iterable<Asset>  $assets
     * @return list<Asset>
     */
    public function refused(iterable $assets): array
    {
        $refused = [];

        foreach ($assets as $asset) {
            if (! $this->allows((string) $asset->containerHandle()) && $this->map->usagesOf($asset) !== []) {
                $refused[] = $asset;
            }
        }

        return $refused;
    }

    /** Whether the current user may delete the container's assets that are in use. */
    private function allows(string $containerHandle): bool
    {
        $user = User::current();

        return match (Settings::deletion($containerHandle)) {
            'block' => false,
            'permission' => $user !== null && ($user->isSuper() || $user->hasPermission(self::PERMISSION)),
            default => true,
        };
    }
}
