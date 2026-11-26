<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Statamic\Facades\Site;

/**
 * The usages of an asset as a tree, in HTML: a root with the number of all places, under it one
 * branch per collection, taxonomy or kind of content, in the order the usages come, each with
 * its number and its entries. Root and branches are the browser's own <details>, so they fold
 * without a script of ours. Every text is escaped here.
 *
 * Shown by the asset editor's "Used in" field and by the delete dialog.
 */
final class Tree
{
    /**
     * @param  list<array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}>  $usages  described usages (Describer)
     * @param  bool  $open  whether the branches start open; the root always does
     * @param  bool  $boxed  keep a long tree in a box that scrolls (the delete dialog)
     */
    public static function render(array $usages, bool $open = true, bool $boxed = false): string
    {
        if ($usages === []) {
            return '<p>'.e(__('whereabouts::messages.not_used')).'</p>';
        }

        $branches = [];

        foreach ($usages as $usage) {
            $branches[$usage['context']][] = $usage;
        }

        $html = '';

        foreach ($branches as $context => $lines) {
            $html .= '<details'.($open ? ' open' : '').' style="margin:.35rem 0 0 1.1rem">'
                .'<summary style="cursor:pointer;font-weight:600">'.e((string) $context).' <span style="opacity:.6;font-weight:400">('.count($lines).')</span></summary>'
                .'<ul style="list-style:none;margin:.25rem 0 0;padding:0 0 0 1.1rem">'.implode('', array_map(self::line(...), $lines)).'</ul>'
                .'</details>';
        }

        return '<details open'.($boxed ? ' style="max-height:18rem;overflow:auto"' : '').'>'
            .'<summary style="cursor:pointer;font-weight:600">'.e(__('whereabouts::messages.all_places')).' <span style="opacity:.6;font-weight:400">('.count($usages).')</span></summary>'
            .$html
            .'</details>';
    }

    /** @param  array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}  $usage */
    private static function line(array $usage): string
    {
        $title = e($usage['title']);

        // A new tab: the editor keeps the asset (or the dialog) open. The colour is the control
        // panel's own accent for text - the theme the user picked, light or dark.
        if ($usage['url'] !== null) {
            $title = '<a href="'.e($usage['url']).'" target="_blank" rel="noopener" style="color:var(--theme-color-ui-accent-text);text-decoration:underline">'.$title.'</a>';
        }

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
