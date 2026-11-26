<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Actions;

use Byte5\Whereabouts\Settings;
use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Tree;
use Byte5\Whereabouts\Usage\UsageMap;
use Illuminate\Support\Collection;
use Statamic\Actions\Delete as StatamicDelete;
use Statamic\Contracts\Assets\Asset;

/**
 * Statamic's delete action, with a warning where an asset that is in use is about to go: a
 * sentence with the number of places and, for one asset, the tree of them with links.
 * Everything else - what it deletes, who may, every other item's dialog - is Statamic's.
 *
 * Statamic builds the warning and the dialog's fields for every row while the asset browser
 * lists a page, so both come from the usage map alone and load no content.
 */
class Delete extends StatamicDelete
{
    /** The handle of the dialog's field that lists the places. */
    public const FIELD = 'whereabouts_usages';

    public function __construct(private readonly UsageMap $map, private readonly Describer $describer)
    {
        parent::__construct();
    }

    /**
     * The dialog's body for one asset in use: the tree of its places, linked.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function fieldItems()
    {
        $asset = $this->items->count() === 1 ? $this->items->first() : null;
        $usages = $asset instanceof Asset ? $this->map->usagesOf($asset) : [];

        if (! $asset instanceof Asset || $usages === []) {
            return [];
        }

        return [
            self::FIELD => [
                'type' => 'html',
                'display' => __('whereabouts::messages.used_in'),
                'html' => Tree::render(
                    array_map($this->describer->sketch(...), $usages),
                    ! Settings::collapsed((string) $asset->containerHandle()),
                    boxed: true,
                ),
                'sanitize' => false,
            ],
        ];
    }

    /** @return string|null */
    public function warningText()
    {
        /** @var Collection<array-key, mixed> $assets */
        $assets = $this->items;

        if ($assets->isEmpty() || $assets->contains(fn ($item): bool => ! $item instanceof Asset)) {
            return parent::warningText();
        }

        $usages = $this->map->usagesOfMany($assets);

        if ($usages === []) {
            return null;
        }

        if ($assets->count() > 1) {
            return __('whereabouts::messages.warning.bulk', ['used' => count($usages), 'selected' => $assets->count()]);
        }

        $places = count(array_values($usages)[0]);

        return __('whereabouts::messages.warning.'.($places === 1 ? 'one_place' : 'many_places'), ['count' => $places]);
    }
}
