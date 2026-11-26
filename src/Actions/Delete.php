<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Actions;

use Byte5\Whereabouts\Protection;
use Byte5\Whereabouts\Settings;
use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Tree;
use Byte5\Whereabouts\Usage\Usage;
use Byte5\Whereabouts\Usage\UsageMap;
use Exception;
use Illuminate\Support\Collection;
use Statamic\Actions\Delete as StatamicDelete;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Assets\AssetFolder;

/**
 * Statamic's delete action, with a warning where an asset that is in use is about to go: a
 * sentence with the number of places and, for one asset, the tree of them with links.
 * Where a container holds a user back (Protection), the sentence says so and the delete is
 * refused - for a selection and for a folder as a whole. Everything else is Statamic's.
 *
 * Statamic builds the warning and the dialog's fields for every row while the asset browser
 * lists a page, so both come from the usage map alone and load no content.
 */
class Delete extends StatamicDelete
{
    /** The handle of the dialog's field that lists the places. */
    public const FIELD = 'whereabouts_usages';

    public function __construct(
        private readonly UsageMap $map,
        private readonly Describer $describer,
        private readonly Protection $protection,
    ) {
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

        return $this->message($assets, $usages, $this->protection->refused($assets));
    }

    /**
     * Statamic's delete, unless the selection holds an asset in use that this user may not
     * delete: then nothing of it goes, and the dialog says why.
     *
     * @param  Collection<array-key, mixed>  $items
     * @param  array<array-key, mixed>  $values
     * @return mixed
     */
    public function run($items, $values)
    {
        if ($items->isNotEmpty() && $items->every(fn ($item): bool => $item instanceof Asset)) {
            if (($refused = $this->protection->refused($items)) !== []) {
                throw new Exception($this->message($items, $this->map->usagesOfMany($items), $refused));
            }
        }

        foreach ($items as $item) {
            if ($item instanceof AssetFolder && ($count = count($this->protection->refused($item->assets(true)))) > 0) {
                throw new Exception(__('whereabouts::messages.refused.'.($count === 1 ? 'folder_one' : 'folder_many'), ['count' => $count]));
            }
        }

        return parent::run($items, $values);
    }

    /**
     * The sentence for a selection of assets of which some are in use: a warning, or a refusal
     * where the user may not delete them.
     *
     * @param  Collection<array-key, mixed>  $assets
     * @param  array<string, list<Usage>>  $usages  by asset id
     * @param  list<Asset>  $refused
     */
    private function message(Collection $assets, array $usages, array $refused): string
    {
        $group = $refused === [] ? 'warning' : 'refused';

        if ($assets->count() > 1) {
            return __("whereabouts::messages.{$group}.bulk", [
                'used' => $refused === [] ? count($usages) : count($refused),
                'selected' => $assets->count(),
            ]);
        }

        $places = count(array_values($usages)[0]);

        return __("whereabouts::messages.{$group}.".($places === 1 ? 'one_place' : 'many_places'), ['count' => $places]);
    }
}
