<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Support\Str;

use function Statamic\trans as __;

/**
 * A usage as an editor reads it: the item's title, what it is part of, where to edit it.
 * Read from the item when a list is shown, so a renamed entry never shows an old title.
 *
 * Titles go through Statamic's translator, not Laravel's helper: a title that happens to name a
 * language file ("Validation") would come back from that as the file's whole array.
 */
class Describer
{
    /**
     * @return array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}|null null when the item is gone
     */
    public function describe(Usage $usage): ?array
    {
        $item = match ($usage->kind) {
            Kind::Entry => $this->entry($usage),
            Kind::Term => $this->term($usage),
            Kind::GlobalSet => $this->globalSet($usage),
            Kind::User => $this->user($usage),
            Kind::Nav => $this->nav($usage),
        };

        if ($item === null) {
            return null;
        }

        [$title, $context, $editable, $url] = $item;

        return [
            'title' => $title,
            'context' => $context,
            'url' => User::current()?->can('edit', $editable) ? $url : null,
            'sites' => array_map(fn (string $handle): string => (string) (Site::get($handle)?->name() ?? $handle), $usage->sites),
            'cleaned' => $usage->cleaned,
        ];
    }

    /**
     * The description of a usage from the map alone - no content is loaded: the title the map
     * holds, the link built from handles and ids, shown where the user's role may edit that kind
     * of content. For lists built per row of the asset browser (the delete dialog). An entry is
     * shown and linked as describe() does: its version in the editor's site, from the map.
     *
     * @return array{title: string, context: string, url: string|null, sites: list<string>, cleaned: bool}
     */
    public function sketch(Usage $usage): array
    {
        $site = $this->site($usage);
        $in = (string) $usage->in;
        $version = $site === null ? null : ($usage->versions[$site] ?? null);

        [$context, $url, $permission] = match ($usage->kind) {
            Kind::Entry => [
                Collection::findByHandle($in)?->title() ?? $in,
                cp_route('collections.entries.edit', [$in, $version['id'] ?? $usage->id]),
                "edit {$in} entries",
            ],
            Kind::Term => [
                Taxonomy::findByHandle($in)?->title() ?? $in,
                cp_route('taxonomies.terms.edit', [$in, Str::after($usage->id, '::'), $site]),
                "edit {$in} terms",
            ],
            Kind::GlobalSet => [
                'whereabouts::messages.kinds.global',
                cp_route('globals.variables.edit', array_filter(['global_set' => $usage->id, 'site' => $site])),
                "edit {$usage->id} globals",
            ],
            Kind::User => [
                'whereabouts::messages.kinds.user',
                cp_route('users.edit', $usage->id),
                'edit users',
            ],
            Kind::Nav => [
                'whereabouts::messages.kinds.nav',
                cp_route('navigation.show', array_filter(['navigation' => $usage->id, 'site' => $site])),
                "edit {$usage->id} nav",
            ],
        };

        return [
            'title' => in_array($usage->kind, [Kind::GlobalSet, Kind::Nav], true) ? (string) __($usage->title) : ($version['title'] ?? $usage->title),
            'context' => (string) __($context),
            'url' => User::current()?->can($permission) ? (string) $url : null,
            'sites' => array_map(fn (string $handle): string => (string) (Site::get($handle)?->name() ?? $handle), $usage->sites),
            'cleaned' => $usage->cleaned,
        ];
    }

    /** The site whose version of the item the link opens: the one the editor works in, where the usage has it. */
    private function site(Usage $usage): ?string
    {
        $selected = (string) Site::selected()->handle();

        return in_array($selected, $usage->sites, true) ? $selected : ($usage->sites[0] ?? null);
    }

    /** @return array{string, string, object, string}|null */
    private function entry(Usage $usage): ?array
    {
        $root = Entry::find($usage->id);

        if ($root === null) {
            return null;
        }

        $site = $this->site($usage);
        $entry = ($site === null ? null : $root->in($site)) ?? $root;

        return [(string) ($entry->value('title') ?? $entry->slug()), (string) __($entry->collection()->title()), $entry, (string) $entry->editUrl()];
    }

    /** @return array{string, string, object, string}|null */
    private function term(Usage $usage): ?array
    {
        $term = Term::find($usage->id);

        if ($term === null) {
            return null;
        }

        $site = $this->site($usage);
        $term = $site === null ? $term : $term->in($site);

        return [(string) $term->title(), (string) __($term->taxonomy()->title()), $term, (string) $term->editUrl()];
    }

    /** @return array{string, string, object, string}|null */
    private function globalSet(Usage $usage): ?array
    {
        $set = GlobalSet::findByHandle($usage->id);

        if ($set === null) {
            return null;
        }

        $site = $this->site($usage);
        $variables = $site === null ? null : $set->in($site);

        return [(string) __($set->title()), (string) __('whereabouts::messages.kinds.global'), $variables ?? $set, (string) ($variables ?? $set)->editUrl()];
    }

    /** @return array{string, string, object, string}|null */
    private function user(Usage $usage): ?array
    {
        $user = User::find($usage->id);

        if ($user === null) {
            return null;
        }

        return [(string) ($user->name() ?: $user->email()), (string) __('whereabouts::messages.kinds.user'), $user, (string) $user->editUrl()];
    }

    /** @return array{string, string, object, string}|null */
    private function nav(Usage $usage): ?array
    {
        $nav = Nav::findByHandle($usage->id);

        if ($nav === null) {
            return null;
        }

        $site = $this->site($usage);

        return [(string) __($nav->title()), (string) __('whereabouts::messages.kinds.nav'), $nav, (string) $nav->showUrl($site === null ? [] : ['site' => $site])];
    }
}
