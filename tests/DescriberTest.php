<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Kind;
use Byte5\Whereabouts\Usage\Usage;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\FakesRoles;

class DescriberTest extends TestCase
{
    use FakesRoles;

    private function describe(Usage $usage): ?array
    {
        return app(Describer::class)->describe($usage);
    }

    private function actAs(bool $super): void
    {
        $user = User::make()->email($super ? 'admin@example.com' : 'guest@example.com');
        $super ? $user->makeSuper() : null;
        $user->save();
        $this->actingAs($user);
    }

    public function test_an_entry_is_described_with_its_collection_and_edit_url(): void
    {
        $this->actAs(super: true);
        $page = $this->page(['title' => 'Start']);

        $this->assertSame(
            ['title' => 'Start', 'context' => 'Pages', 'url' => $page->editUrl(), 'sites' => ['Deutsch'], 'cleaned' => true],
            $this->describe(new Usage(Kind::Entry, $page->id(), ['de'], true)),
        );
    }

    public function test_the_link_goes_to_the_selected_sites_localisation(): void
    {
        $this->actAs(super: true);
        $de = $this->page(['title' => 'Start']);
        $en = $this->page(['title' => 'Home'], 'en', $de);
        Site::setSelected('en');

        $described = $this->describe(new Usage(Kind::Entry, $de->id(), ['de', 'en'], true));

        $this->assertSame($en->editUrl(), $described['url']);
        $this->assertSame('Home', $described['title']);
        $this->assertSame(['Deutsch', 'English'], $described['sites']);
    }

    public function test_no_url_without_the_permission_to_edit(): void
    {
        $this->actAs(super: false);
        $page = $this->page(['title' => 'Start']);

        $described = $this->describe(new Usage(Kind::Entry, $page->id(), ['de'], true));

        $this->assertNull($described['url']);
        $this->assertSame('Start', $described['title']);
    }

    public function test_a_usage_whose_item_is_gone_is_null(): void
    {
        $this->actAs(super: true);

        $this->assertNull($this->describe(new Usage(Kind::Entry, 'no-such-id', ['de'], true)));
        $this->assertNull($this->describe(new Usage(Kind::Term, 'tags::gone', ['de'], true)));
        $this->assertNull($this->describe(new Usage(Kind::GlobalSet, 'gone', ['de'], true)));
        $this->assertNull($this->describe(new Usage(Kind::User, 'gone', [], true)));
        $this->assertNull($this->describe(new Usage(Kind::Nav, 'gone', ['de'], false)));
    }

    public function test_a_sketch_matches_the_description_without_loading_the_item(): void
    {
        // The user blueprint first: Statamic keeps the one it found when the first user was made.
        $icon = ['icon' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]];
        $this->blueprint('taxonomies/tags/tag', $icon);
        $this->blueprint('globals/settings', $icon);
        $this->blueprint('navigation/main', $icon);
        $this->blueprint('user', $icon);
        $this->actAs(super: true);
        Taxonomy::make('tags')->title('Tags')->sites(['de', 'en'])->save();
        Term::make()->taxonomy('tags')->slug('news')->blueprint('tag')->dataForLocale('de', ['title' => 'Neuigkeiten', 'icon' => 'a.jpg'])->save();
        $set = GlobalSet::make('settings')->title('Settings')->sites(['de' => null, 'en' => 'de']);
        $set->save();
        $set->in('de')->data(['icon' => 'a.jpg'])->save();
        $nav = Nav::make('main')->title('Main');
        $nav->save();
        $nav->makeTree('de', [['id' => 'n1', 'title' => 'Top', 'url' => '/top', 'data' => ['icon' => 'a.jpg']]])->save();
        User::make()->email('jo@example.com')->data(['name' => 'Jo', 'icon' => 'a.jpg'])->save();
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $usages = app(UsageMap::class)->usagesOf($this->asset('images', 'a.jpg'));

        $this->assertSame(['entry', 'term', 'global', 'user', 'nav'], array_map(fn (Usage $usage) => $usage->kind->value, $usages));
        foreach ($usages as $usage) {
            $this->assertSame($this->describe($usage), app(Describer::class)->sketch($usage), $usage->kind->value);
        }
    }

    public function test_a_sketch_shows_and_links_the_version_in_the_editors_site(): void
    {
        $this->actAs(super: true);
        $de = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $en = $this->page(['title' => 'Home'], 'en', $de);
        [$usage] = app(UsageMap::class)->usagesOf($this->asset('images', 'a.jpg'));

        $this->assertSame(['Start', $de->editUrl()], array_values(array_intersect_key(app(Describer::class)->sketch($usage), ['title' => 0, 'url' => 0])));

        Site::setSelected('en');

        $this->assertSame(['Home', $en->editUrl()], array_values(array_intersect_key(app(Describer::class)->sketch($usage), ['title' => 0, 'url' => 0])));
        $this->assertSame($this->describe($usage), app(Describer::class)->sketch($usage));
    }

    public function test_a_sketch_has_no_url_without_the_permission(): void
    {
        $this->actAs(super: false);
        $page = $this->page(['title' => 'Start']);

        $sketch = app(Describer::class)->sketch(new Usage(Kind::Entry, $page->id(), ['de'], true, 'Start', 'pages'));

        $this->assertNull($sketch['url']);
        $this->assertSame(['Start', 'Pages'], [$sketch['title'], $sketch['context']]);
    }

    public function test_a_sketch_links_what_the_users_role_may_edit(): void
    {
        $this->setTestRoles(['editor' => ['access cp', 'edit pages entries']]);
        $user = User::make()->email('editor@example.com')->assignRole('editor');
        $user->save();
        $this->actingAs($user);
        $page = $this->page(['title' => 'Start']);

        $this->assertSame($page->editUrl(), app(Describer::class)->sketch(new Usage(Kind::Entry, $page->id(), ['de'], true, 'Start', 'pages'))['url']);
        $this->assertNull(app(Describer::class)->sketch(new Usage(Kind::Entry, 'x', ['de'], true, 'Other', 'news'))['url']);
    }

    public function test_a_nav_is_described_as_navigation_and_not_cleaned(): void
    {
        $this->actAs(super: true);
        $nav = Nav::make('main')->title('Main');
        $nav->save();
        $nav->makeTree('de', [])->save();

        $this->assertSame(
            ['title' => 'Main', 'context' => 'Navigation', 'url' => $nav->showUrl(['site' => 'de']), 'sites' => ['Deutsch'], 'cleaned' => false],
            $this->describe(new Usage(Kind::Nav, 'main', ['de'], false)),
        );
    }
}
