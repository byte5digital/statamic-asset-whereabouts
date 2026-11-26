<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Fieldtypes;

use Byte5\Whereabouts\Listeners\AddUsedInField;
use Byte5\Whereabouts\Settings;
use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Tree;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Blink;
use Statamic\Fieldtypes\Html;

/**
 * The "Used in" field of the asset editor: Statamic's html fieldtype, shown by its stock
 * component, whose markup is not the blueprint's but the list of the asset's usages. Computed
 * when the editor's form is built, never saved (Listeners\AddUsedInField sets it up).
 */
class UsedIn extends Html
{
    /** @var string */
    protected static $handle = 'whereabouts_used_in';

    /** @var bool */
    protected $selectable = false;

    /** The stock html component shows it: no control panel build of ours. */
    public function component(): string
    {
        return 'html';
    }

    /**
     * The field's config with the html written here; never sanitised, every text in it is escaped.
     *
     * @param  mixed  $fallback
     * @return mixed
     */
    public function config(?string $key = null, $fallback = null)
    {
        $config = parent::config();

        if (! is_array($config)) {
            return $key === null ? $config : $fallback;
        }

        $config['html'] = $this->list();
        $config['sanitize'] = false;

        return $key === null ? $config : ($config[$key] ?? $fallback);
    }

    /** The tree of the opened asset's usages, each read from its item: current titles, links by permission. */
    private function list(): string
    {
        $asset = $this->field()?->parent();
        $asset = $asset instanceof Asset ? $asset : Blink::get(AddUsedInField::OPENED);

        if (! $asset instanceof Asset) {
            return Tree::render([]);
        }

        $describer = resolve(Describer::class);
        $described = [];

        foreach (resolve(UsageMap::class)->usagesOf($asset) as $usage) {
            if (($usage = $describer->describe($usage)) !== null) {
                $described[] = $usage;
            }
        }

        return Tree::render($described, ! Settings::collapsed((string) $asset->containerHandle()));
    }
}
