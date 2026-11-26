<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Fieldtypes;

use Byte5\Whereabouts\Listeners\AddUsedInField;
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

    private function list(): string
    {
        $asset = $this->field()?->parent();
        $asset = $asset instanceof Asset ? $asset : Blink::get(AddUsedInField::OPENED);
        $describer = app(Describer::class);
        $lines = [];

        foreach ($asset instanceof Asset ? app(UsageMap::class)->usagesOf($asset) : [] as $usage) {
            if (($described = $describer->describe($usage)) !== null) {
                $lines[] = $this->line($described);
            }
        }

        if ($lines === []) {
            return '<p>'.e(__('whereabouts::messages.not_used')).'</p>';
        }

        return '<ul style="list-style:none;margin:0;padding:0">'.implode('', $lines).'</ul>';
    }

    /** @param  array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}  $usage */
    private function line(array $usage): string
    {
        $title = e($usage['title']);
        $title = $usage['url'] === null ? $title : '<a href="'.e($usage['url']).'">'.$title.'</a>';

        $details = [e($usage['context'])];

        if ($usage['sites'] !== [] && Site::all()->count() > 1) {
            $details[] = e(count($usage['sites']) === Site::all()->count()
                ? __('whereabouts::messages.all_sites')
                : implode(', ', $usage['sites']));
        }

        $note = $usage['cleaned'] ? '' : ' ('.e(__('whereabouts::messages.stays_after_delete')).')';

        return '<li style="padding:.25rem 0">'.$title.' <span style="opacity:.7">· '.implode(' · ', $details).$note.'</span></li>';
    }
}
