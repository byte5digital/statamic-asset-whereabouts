<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\Kind;
use Byte5\Whereabouts\Usage\MapBuilder;
use Byte5\Whereabouts\Usage\UsageMap;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class UsageMapTest extends TestCase
{
    private function map(): UsageMap
    {
        return app(UsageMap::class);
    }

    /** Counts how often the content is walked. */
    private function spyOnBuilds(): object
    {
        $spy = new class extends MapBuilder
        {
            public int $builds = 0;

            public function build(): array
            {
                $this->builds++;

                return parent::build();
            }
        };
        $this->app->instance(MapBuilder::class, $spy);
        $this->app->forgetInstance(UsageMap::class);

        return $spy;
    }

    public function test_it_answers_for_one_asset_and_for_many(): void
    {
        $used = $this->asset('images', 'a.jpg');
        $unused = $this->asset('images', 'b.jpg');
        $page = $this->page(['image' => 'a.jpg']);

        $usages = $this->map()->usagesOf($used);

        $this->assertCount(1, $usages);
        $this->assertSame(Kind::Entry, $usages[0]->kind);
        $this->assertSame($page->id(), $usages[0]->id);
        $this->assertSame([], $this->map()->usagesOf($unused));
        $this->assertSame(['images::a.jpg'], array_keys($this->map()->usagesOfMany([$used, $unused])));
    }

    public function test_a_second_question_does_not_walk_the_content_again(): void
    {
        $spy = $this->spyOnBuilds();
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['image' => 'a.jpg']);

        $this->map()->usagesOf($asset);
        $this->map()->usagesOf($asset);
        $this->app->forgetInstance(UsageMap::class);
        $this->assertCount(1, $this->map()->usagesOf($asset));

        $this->assertSame(1, $spy->builds);
    }

    public function test_saving_content_forgets_the_map(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->assertSame([], $this->map()->usagesOf($asset));

        $page = $this->page(['image' => 'a.jpg']);
        $this->assertCount(1, $this->map()->usagesOf($asset));

        $page->delete();
        $this->assertSame([], $this->map()->usagesOf($asset));
    }

    public function test_a_saved_term_nav_tree_and_asset_forget_the_map(): void
    {
        $icon = ['icon' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]];
        $this->blueprint('taxonomies/tags/tag', $icon);
        $this->blueprint('navigation/main', $icon);
        Taxonomy::make('tags')->sites(['de', 'en'])->save();
        $nav = Nav::make('main')->title('Main');
        $nav->save();
        $asset = $this->asset('images', 'a.jpg');
        $spy = $this->spyOnBuilds();

        $this->assertCount(0, $this->map()->usagesOf($asset));

        Term::make()->taxonomy('tags')->slug('news')->blueprint('tag')->dataForLocale('de', ['icon' => 'a.jpg'])->save();
        $this->assertCount(1, $this->map()->usagesOf($asset));

        $nav->makeTree('de', [['id' => 'n1', 'title' => 'Top', 'url' => '/top', 'data' => ['icon' => 'a.jpg']]])->save();
        $this->assertCount(2, $this->map()->usagesOf($asset));

        $this->asset('images', 'other.jpg');
        $this->map()->usagesOf($asset);
        $this->assertSame(4, $spy->builds);
    }

    public function test_the_map_survives_a_cache_that_restores_only_arrays(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['image' => 'a.jpg']);
        $this->map()->usagesOf($asset);

        $raw = Cache::get('whereabouts.usage-map');

        $this->assertIsArray($raw);
        $this->assertSame($raw, json_decode((string) json_encode($raw), true));

        $this->app->forgetInstance(UsageMap::class);
        $this->assertCount(1, $this->map()->usagesOf($asset));
    }

    public function test_a_container_left_out_in_the_settings_has_no_usages(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['image' => 'a.jpg']);
        config(['whereabouts.containers' => ['files']]);

        $this->assertSame([], $this->map()->usagesOf($asset));
        $this->assertSame([], $this->map()->usagesOfMany([$asset]));
    }
}
