<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\UsageMap;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Facades\User;

class UsedInFieldTest extends TestCase
{
    private const FIELD = 'whereabouts_used_in';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::make()->email('admin@example.com')->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    private function html(Asset $asset): string
    {
        return $asset->blueprint()->field(self::FIELD)->toPublishArray()['html'];
    }

    public function test_an_opened_asset_has_the_field_in_a_section_of_its_own(): void
    {
        $blueprint = $this->asset('images', 'a.jpg')->blueprint();

        $this->assertTrue($blueprint->hasField(self::FIELD));
        $this->assertSame('computed', $blueprint->field(self::FIELD)->visibility());

        $sections = collect($blueprint->contents()['tabs'])->first()['sections'];
        $this->assertSame('Used in', end($sections)['display']);
        $this->assertSame([self::FIELD], array_column(end($sections)['fields'], 'handle'));
    }

    public function test_opening_two_assets_adds_the_field_once(): void
    {
        $this->asset('images', 'a.jpg')->blueprint();
        $blueprint = $this->asset('images', 'b.jpg')->blueprint();

        $this->assertSame(1, $blueprint->fields()->all()->filter(fn ($field) => $field->handle() === self::FIELD)->count());
    }

    public function test_the_containers_blueprint_without_an_opened_asset_has_not(): void
    {
        $this->asset('images', 'a.jpg');
        Blink::flush();

        $this->assertFalse(AssetContainer::find('images')->blueprint()->hasField(self::FIELD));
    }

    public function test_a_container_left_out_in_the_settings_has_no_field(): void
    {
        config(['whereabouts.containers' => ['files']]);

        $this->assertFalse($this->asset('images', 'a.jpg')->blueprint()->hasField(self::FIELD));
    }

    public function test_the_field_lists_the_usages_with_links(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $page = $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $html = $this->html($asset);

        $this->assertStringContainsString('<a href="'.e($page->editUrl()).'" style="text-decoration:underline">Start</a>', $html);
        $this->assertStringContainsString('Pages', $html);
        $this->assertStringContainsString('Deutsch', $html);
        $this->assertStringNotContainsString('Not used', $html);
    }

    public function test_a_usage_on_every_site_says_all_sites(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $de = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->page(['title' => 'Home'], 'en', $de);

        $this->assertStringContainsString('all sites', $this->html($asset));
    }

    public function test_a_title_is_escaped(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => '<b>x</b>', 'image' => 'a.jpg']);

        $html = $this->html($asset);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function test_an_unused_asset_says_so(): void
    {
        $this->assertStringContainsString('Not used in any content.', $this->html($this->asset('images', 'a.jpg')));
    }

    public function test_a_deleted_item_is_left_out_of_the_list(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        Cache::put(UsageMap::CACHE_KEY, ['images::a.jpg' => [['kind' => 'entry', 'id' => 'gone', 'sites' => ['de'], 'cleaned' => true]]], 60);
        $this->app->forgetInstance(UsageMap::class);

        $this->assertStringContainsString('Not used in any content.', $this->html($asset));
    }

    public function test_the_asset_editor_gets_the_list_and_saving_stores_nothing_of_it(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $url = cp_route('assets.update', base64_encode($asset->id()));

        $shown = $this->getJson(cp_route('assets.show', base64_encode($asset->id())))->assertOk()->json('data');
        $field = collect($shown['blueprint']['tabs'])->flatMap(fn ($tab) => $tab['sections'])->flatMap(fn ($section) => $section['fields'])
            ->firstWhere('handle', self::FIELD);
        $this->assertStringContainsString('Start', $field['html']);

        $this->patchJson($url, ['alt' => 'An image', self::FIELD => 'x'])->assertOk();

        $this->assertArrayNotHasKey(self::FIELD, \Statamic\Facades\Asset::find($asset->id())->data()->all());
    }
}
