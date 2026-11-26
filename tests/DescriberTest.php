<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\Kind;
use Byte5\Whereabouts\Usage\Usage;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\User;

class DescriberTest extends TestCase
{
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
