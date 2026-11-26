<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Fieldtypes;

use Byte5\Whereabouts\Listeners\AddUsedInField;
use Byte5\Whereabouts\Settings;
use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Blink;
use Statamic\Facades\Site;
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

    /**
     * A tree: a root with the number of all places, under it one branch per collection, taxonomy
     * or kind of content, in the order the usages come, each with its number and its entries. Branches are the browser's
     * own <details>, so an editor can fold a long one away; a container's setting decides whether
     * they start open (the default) or collapsed.
     */
    private function list(): string
    {
        $asset = $this->field()?->parent();
        $asset = $asset instanceof Asset ? $asset : Blink::get(AddUsedInField::OPENED);

        $describer = resolve(Describer::class);
        $branches = [];

        if (! $asset instanceof Asset) {
            return $this->notUsed();
        }

        foreach (resolve(UsageMap::class)->usagesOf($asset) as $usage) {
            if (($described = $describer->describe($usage)) !== null) {
                $branches[$usage->kind->value.'|'.$described['context']][] = $described;
            }
        }

        if ($branches === []) {
            return $this->notUsed();
        }

        $open = ! Settings::collapsed((string) $asset->containerHandle());
        $total = array_sum(array_map(count(...), $branches));
        $html = '';

        foreach ($branches as $usages) {
            $html .= '<details'.($open ? ' open' : '').' style="margin:.35rem 0 0 1.1rem">'
                .'<summary style="cursor:pointer;font-weight:600">'.e($usages[0]['context']).' <span style="opacity:.6;font-weight:400">('.count($usages).')</span></summary>'
                .'<ul style="list-style:none;margin:.25rem 0 0;padding:0 0 0 1.1rem">'.implode('', array_map($this->line(...), $usages)).'</ul>'
                .'</details>';
        }

        // The root: every place together. Always open, so the branches' headings show.
        return '<details open>'
            .'<summary style="cursor:pointer;font-weight:600">'.e(__('whereabouts::messages.all_places')).' <span style="opacity:.6;font-weight:400">('.$total.')</span></summary>'
            .$html
            .'</details>';
    }

    private function notUsed(): string
    {
        return '<p>'.e(__('whereabouts::messages.not_used')).'</p>';
    }

    /** @param  array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}  $usage */
    private function line(array $usage): string
    {
        $title = e($usage['title']);
        // The control panel's own accent colour for text: the theme the user picked, light or dark.
        $title = $usage['url'] === null ? $title : '<a href="'.e($usage['url']).'" style="color:var(--theme-color-ui-accent-text);text-decoration:underline">'.$title.'</a>';

        $details = [];

        if ($usage['sites'] !== [] && Site::all()->count() > 1) {
            $details[] = e(count($usage['sites']) === Site::all()->count()
                ? __('whereabouts::messages.all_sites')
                : implode(', ', $usage['sites']));
        }

        if (! $usage['cleaned']) {
            $details[] = e(__('whereabouts::messages.stays_after_delete'));
        }

        $details = $details === [] ? '' : ' <span style="opacity:.7">· '.implode(' · ', $details).'</span>';

        return '<li style="padding:.2rem 0">'.$title.$details.'</li>';
    }
}
