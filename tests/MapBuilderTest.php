<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\MapBuilder;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;

class MapBuilderTest extends TestCase
{
    /** @return array<string, list<array{kind: string, id: string, sites: list<string>, cleaned: bool}>> */
    private function build(): array
    {
        return app(MapBuilder::class)->build();
    }

    /** The usages of an asset without their titles. */
    private function bare(string $assetId): array
    {
        return array_map(fn (array $usage) => array_diff_key($usage, ['title' => 0, 'in' => 0, 'versions' => 0]), $this->build()[$assetId]);
    }

    public function test_an_entry_is_listed_once_with_its_site(): void
    {
        $page = $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $this->assertSame(
            [['kind' => 'entry', 'id' => $page->id(), 'sites' => ['de'], 'cleaned' => true, 'title' => 'Start', 'in' => 'pages', 'versions' => ['de' => ['id' => $page->id(), 'title' => 'Start']]]],
            $this->build()['images::a.jpg'],
        );
    }

    public function test_a_localisation_that_inherits_the_field_adds_its_site(): void
    {
        $de = $this->page(['image' => 'a.jpg']);
        $this->page(['title' => 'En'], 'en', $de);

        $this->assertSame(
            [['kind' => 'entry', 'id' => $de->id(), 'sites' => ['de', 'en'], 'cleaned' => true]],
            $this->bare('images::a.jpg'),
        );
    }

    public function test_a_localisation_that_overrides_the_field_is_listed_for_its_own_asset(): void
    {
        $de = $this->page(['image' => 'a.jpg']);
        $this->page(['image' => 'b.jpg'], 'en', $de);
        $this->assertSame([['kind' => 'entry', 'id' => $de->id(), 'sites' => ['de'], 'cleaned' => true]], $this->bare('images::a.jpg'));
        $this->assertSame([['kind' => 'entry', 'id' => $de->id(), 'sites' => ['en'], 'cleaned' => true]], $this->bare('images::b.jpg'));
    }

    public function test_an_unpublished_entry_counts(): void
    {
        $page = $this->page(['image' => 'a.jpg'], published: false);

        $this->assertSame($page->id(), $this->build()['images::a.jpg'][0]['id']);
    }

    public function test_a_working_copy_that_alone_holds_the_reference_counts(): void
    {
        config(['statamic.revisions.enabled' => true, 'statamic.revisions.path' => $this->blueprints.'/revisions']);
        Collection::find('pages')->revisionsEnabled(true)->save();
        $page = $this->page(['title' => 'Live']);
        $draft = clone $page;
        $draft->data(['title' => 'Draft', 'image' => 'a.jpg']);
        $draft->makeWorkingCopy()->save();

        $this->assertNull($page->get('image'));
        $this->assertSame(
            [['kind' => 'entry', 'id' => $page->id(), 'sites' => ['de'], 'cleaned' => true]],
            $this->bare('images::a.jpg'),
        );
    }

    public function test_a_term_a_global_set_and_a_user_are_listed(): void
    {
        $icon = ['icon' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]];
        $this->blueprint('taxonomies/tags/tag', $icon);
        $this->blueprint('globals/settings', $icon);
        $this->blueprint('user', $icon);

        Taxonomy::make('tags')->sites(['de', 'en'])->save();
        Term::make()->taxonomy('tags')->slug('news')->blueprint('tag')->dataForLocale('de', ['icon' => 'a.jpg'])->save();

        $set = GlobalSet::make('settings')->title('Settings')->sites(['de' => null, 'en' => 'de']);
        $set->save();
        $set->in('de')->data(['icon' => 'a.jpg'])->save();

        $user = User::make()->email('jo@example.com')->data(['icon' => 'a.jpg']);
        $user->save();

        $this->assertSame([
            ['kind' => 'term', 'id' => 'tags::news', 'sites' => ['de', 'en'], 'cleaned' => true],
            ['kind' => 'global', 'id' => 'settings', 'sites' => ['de', 'en'], 'cleaned' => true],
            ['kind' => 'user', 'id' => (string) $user->id(), 'sites' => [], 'cleaned' => true],
        ], $this->bare('images::a.jpg'));
    }

    public function test_a_nav_item_is_listed_and_not_cleaned(): void
    {
        $this->blueprint('navigation/main', ['icon' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]]);
        $nav = Nav::make('main')->title('Main');
        $nav->save();
        $nav->makeTree('de', [
            ['id' => 'n1', 'title' => 'Top', 'url' => '/top', 'children' => [
                ['id' => 'n2', 'title' => 'Child', 'url' => '/child', 'data' => ['icon' => 'a.jpg']],
            ]],
        ])->save();
        $nav->makeTree('en', [['id' => 'n3', 'title' => 'Top', 'url' => '/en/top']])->save();

        $this->assertSame(
            [['kind' => 'nav', 'id' => 'main', 'sites' => ['de'], 'cleaned' => false]],
            $this->bare('images::a.jpg'),
        );
    }

    public function test_a_usage_carries_its_title_and_where_it_belongs(): void
    {
        $this->blueprint('taxonomies/tags/tag', ['icon' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]]);
        Taxonomy::make('tags')->sites(['de', 'en'])->save();
        Term::make()->taxonomy('tags')->slug('news')->blueprint('tag')->dataForLocale('de', ['title' => 'Neuigkeiten', 'icon' => 'a.jpg'])->save();
        $de = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->page(['title' => 'Home'], 'en', $de);

        $usages = collect($this->build()['images::a.jpg'])->keyBy('kind');

        $this->assertSame(['Start', 'pages'], [$usages['entry']['title'], $usages['entry']['in']], 'the root\'s title');
        $this->assertSame(['Neuigkeiten', 'tags'], [$usages['term']['title'], $usages['term']['in']]);
    }

    public function test_an_asset_nobody_references_is_absent(): void
    {
        $this->page(['image' => 'a.jpg']);

        $this->assertArrayNotHasKey('images::zzz.jpg', $this->build());
    }

    public function test_data_that_does_not_match_the_blueprint_does_not_stop_the_build(): void
    {
        $this->page(['blocks' => 'not a list', 'unknown_key' => ['x'], 'image' => 'a.jpg']);
        $this->page(['gallery' => ['b.jpg']]);
        $map = $this->build();

        $this->assertArrayHasKey('images::a.jpg', $map);
        $this->assertArrayHasKey('images::b.jpg', $map);
    }
}
