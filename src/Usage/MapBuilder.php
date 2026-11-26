<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Throwable;

/**
 * One pass over all content: which asset is used where.
 *
 * It visits what Statamic's own clean-up visits when an asset is deleted - entries (and the
 * working copy of one that has one), terms, global sets and users - plus the navigations, which
 * Statamic leaves alone. An item that cannot be read is reported and skipped.
 */
class MapBuilder
{
    /** @var array<string, array<string, array{kind: Kind, id: string, sites: array<string, true>, title: string, in: string|null}>> */
    private array $map = [];

    /**
     * @return array<string, list<array{kind: string, id: string, sites: list<string>, cleaned: bool, title: string, in: string|null}>> asset id => usages
     */
    public function build(): array
    {
        $this->map = [];

        $this->entries();
        $this->terms();
        $this->globals();
        $this->users();
        $this->navs();

        $order = Site::all()->keys()->all();
        $result = [];

        foreach ($this->map as $assetId => $usages) {
            foreach ($usages as $usage) {
                $sites = array_values(array_filter($order, fn (string $site): bool => isset($usage['sites'][$site])));
                $result[$assetId][] = (new Usage($usage['kind'], $usage['id'], $sites, $usage['kind'] !== Kind::Nav, $usage['title'], $usage['in']))->toArray();
            }
        }

        $this->map = [];

        return $result;
    }

    /** One usage per entry with all its localisations: a site counts where the page shows the asset, inherited or its own. */
    private function entries(): void
    {
        foreach (Entry::query()->lazy() as $entry) {
            $this->guarded(function () use ($entry): void {
                $root = $entry->root();
                $id = (string) $root->id();
                $site = (string) $entry->locale();
                $title = (string) ($root->value('title') ?? $root->slug());
                $in = (string) $entry->collectionHandle();

                $this->record(Kind::Entry, $id, $site, $entry->values(), $entry->blueprint(), $title, $in);

                // A draft that alone holds the asset: deleting the asset would empty the draft too.
                // Asked for once: a lookup per entry of a collection with revisions.
                if ($entry->revisionsEnabled() && ($workingCopy = $entry->workingCopy()) !== null) {
                    $draft = $entry->makeFromRevision($workingCopy);
                    $this->record(Kind::Entry, $id, $site, $draft->values(), $entry->blueprint(), $title, $in);
                }
            });
        }
    }

    private function terms(): void
    {
        foreach (Term::query()->lazy() as $term) {
            $this->guarded(fn () => $this->record(Kind::Term, (string) $term->id(), (string) $term->locale(), $term->values(), $term->blueprint(), (string) $term->title(), (string) $term->taxonomyHandle()));
        }
    }

    private function globals(): void
    {
        foreach (GlobalSet::all() as $set) {
            foreach ($set->localizations() as $variables) {
                $this->guarded(fn () => $this->record(Kind::GlobalSet, (string) $set->handle(), (string) $variables->locale(), $variables->values(), $set->blueprint(), (string) $set->title()));
            }
        }
    }

    private function users(): void
    {
        foreach (User::query()->lazy() as $user) {
            $this->guarded(fn () => $this->record(Kind::User, (string) $user->id(), null, $user->data(), $user->blueprint(), (string) ($user->name() ?: $user->email())));
        }
    }

    /** Every item of every site's tree, down through the children. */
    private function navs(): void
    {
        foreach (Nav::all() as $nav) {
            foreach ($nav->trees() as $site => $tree) {
                $this->guarded(function () use ($nav, $site, $tree): void {
                    $this->branches($tree->tree(), fn (array $data) => $this->record(Kind::Nav, (string) $nav->handle(), (string) $site, collect($data), $nav->blueprint(), (string) $nav->title()));
                });
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $branches
     * @param  callable(array<array-key, mixed>): void  $each
     */
    private function branches(array $branches, callable $each): void
    {
        foreach ($branches as $branch) {
            if (! is_array($branch)) {
                continue;
            }

            if (is_array($branch['data'] ?? null)) {
                $each($branch['data']);
            }

            if (is_array($branch['children'] ?? null)) {
                $this->branches($branch['children'], $each);
            }
        }
    }

    /** @param  Collection<array-key, mixed>  $data */
    private function record(Kind $kind, string $id, ?string $site, Collection $data, ?Blueprint $blueprint, string $title, ?string $in = null): void
    {
        if (! $blueprint instanceof Blueprint) {
            return;
        }

        foreach (array_keys(Collector::item(new Subject($data, $blueprint))->references()) as $assetId) {
            $key = $kind->value.'|'.$id;
            $this->map[$assetId][$key] ??= ['kind' => $kind, 'id' => $id, 'sites' => [], 'title' => $title, 'in' => $in];

            if ($site !== null) {
                $this->map[$assetId][$key]['sites'][$site] = true;
            }
        }
    }

    private function guarded(callable $read): void
    {
        try {
            $read();
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
