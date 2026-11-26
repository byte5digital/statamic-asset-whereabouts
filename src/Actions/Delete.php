<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Actions;

use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Usage;
use Byte5\Whereabouts\Usage\UsageMap;
use Illuminate\Support\Collection;
use Statamic\Actions\Delete as StatamicDelete;
use Statamic\Contracts\Assets\Asset;

/**
 * Statamic's delete action, with a warning where an asset that is in use is about to go.
 * Everything else - what it deletes, who may, every other item's dialog - is Statamic's.
 *
 * Statamic builds this text for every row while the asset browser lists a page, so it answers
 * from the usage map alone and loads no content.
 */
class Delete extends StatamicDelete
{
    /** How many places a warning names before it counts the rest. */
    private const NAMED = 5;

    public function __construct(private readonly UsageMap $map, private readonly Describer $describer)
    {
        parent::__construct();
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

        return $this->forOne(array_values($usages)[0]);
    }

    /** @param  list<Usage>  $usages */
    private function forOne(array $usages): string
    {
        // The control panel's pluraliser splits a text at the pipe.
        $titles = array_map(
            fn (Usage $usage): string => str_replace('|', '/', $this->describer->label($usage)),
            array_slice($usages, 0, self::NAMED),
        );

        $list = implode(', ', $titles);

        if (($more = count($usages) - count($titles)) > 0) {
            $list .= ' '.__('whereabouts::messages.warning.more', ['count' => $more]);
        }

        return __('whereabouts::messages.warning.'.(count($usages) === 1 ? 'one_place' : 'many_places'), [
            'count' => count($usages),
            'titles' => $list,
        ]);
    }
}
