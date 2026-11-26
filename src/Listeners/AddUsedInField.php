<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Listeners;

use Byte5\Whereabouts\Settings;
use Statamic\Contracts\Assets\Asset;
use Statamic\Events\AssetContainerBlueprintFound;
use Statamic\Facades\Blink;

/**
 * Adds the "Used in" field to the blueprint of an asset that is being opened, in a section of
 * its own - no blueprint file is edited.
 *
 * Only where the event carries an asset. Statamic fires it without one for the asset browser's
 * columns and for the blueprint editor, where the field must not show: a saved blueprint would
 * write it into the YAML. The blueprint is one instance per container and request, so in a
 * request that opened an asset the field stays on it; the browser and the blueprint editor
 * never open one.
 */
class AddUsedInField
{
    public const HANDLE = 'whereabouts_used_in';

    /** The Blink key of the asset whose blueprint was asked for last in this request. */
    public const OPENED = 'whereabouts.opened-asset';

    public function handle(AssetContainerBlueprintFound $event): void
    {
        $asset = $event->asset;

        if (! $asset instanceof Asset || ! Settings::includes((string) $asset->containerHandle())) {
            return;
        }

        // The editor's form is built from the blueprint's tabs, whose fields know no parent:
        // the fieldtype asks here which asset is being opened.
        Blink::put(self::OPENED, $asset);

        $blueprint = $event->blueprint;

        if ($blueprint->hasField(self::HANDLE)) {
            return;
        }

        $contents = $blueprint->contents();
        $tab = array_key_first($contents['tabs'] ?? []) ?? 'main';

        $contents['tabs'][$tab]['sections'][] = [
            'display' => __('whereabouts::messages.used_in'),
            'fields' => [[
                'handle' => self::HANDLE,
                'field' => ['type' => self::HANDLE, 'visibility' => 'computed', 'hide_display' => true, 'listable' => 'hidden'],
            ]],
        ];

        $blueprint->setContents($contents);
    }
}
