<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\UsageMap;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
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

        $this->assertStringContainsString('<a href="'.e($page->editUrl()).'" target="_blank" rel="noopener" style="color:var(--theme-color-ui-accent-text);text-decoration:underline">Start</a>', $html);
        $this->assertStringContainsString('Pages', $html);
        $this->assertStringContainsString('Deutsch', $html);
        $this->assertStringNotContainsString('Not used', $html);
    }

    public function test_the_usages_are_a_tree_of_collections_and_their_entries(): void
    {
        $this->blueprint('collections/news/article', ['title' => ['type' => 'text'], 'image' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]]);
        $this->blueprint('globals/settings', ['logo' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1]]);
        Collection::make('news')->title('News')->sites(['de', 'en'])->save();
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->page(['title' => 'About', 'image' => 'a.jpg']);
        $article = Entry::make()->id('article-1')->collection('news')->blueprint('article')->locale('de')->slug('fair')->data(['title' => 'Fair', 'image' => 'a.jpg']);
        $article->save();
        $set = GlobalSet::make('settings')->title('Settings')->sites(['de' => null, 'en' => 'de']);
        $set->save();
        $set->in('de')->data(['logo' => 'a.jpg'])->save();

        $html = $this->html($asset);

        // A root with the number of all places, then one branch per collection (or kind), its heading
        // with its own number, its entries under it.
        $this->assertSame(4, substr_count($html, '<details'));
        $this->assertMatchesRegularExpression('#^<details open[^>]*><summary[^>]*>All places <span[^>]*>\(4\)</span></summary>.*</details>$#s', $html);
        $this->assertMatchesRegularExpression('#<summary[^>]*>.*Pages.*\(2\).*</summary>.*Start.*About.*</details>.*<summary[^>]*>.*News.*\(1\).*</summary>.*Fair.*</details>.*<summary[^>]*>.*Global set.*\(1\).*</summary>.*Settings.*</details>#s', $html);
        // The collection is the branch, so a line no longer repeats it.
        $this->assertSame(1, substr_count($html, 'Pages'));
    }

    public function test_a_container_decides_whether_its_branches_start_collapsed(): void
    {
        $image = $this->asset('images', 'a.jpg');
        $file = $this->asset('files', 'a.pdf');
        $this->page(['title' => 'Start', 'image' => 'a.jpg', 'document' => 'a.pdf']);
        config(['whereabouts.containers' => ['images', 'files' => ['branches' => 'collapsed']]]);

        $this->assertSame(2, substr_count($this->html($image), '<details open'), 'the root and its branch');

        // The root stays open, so the branches' headings show; the branches are folded.
        $collapsed = $this->html($file);
        $this->assertSame(2, substr_count($collapsed, '<details'));
        $this->assertSame(1, substr_count($collapsed, '<details open'));
        $this->assertStringStartsWith('<details open', $collapsed);
        $this->assertStringContainsString('Start', $collapsed);
    }

    public function test_a_usage_on_every_site_says_all_sites(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $de = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->page(['title' => 'Home'], 'en', $de);

        $this->assertStringContainsString('all sites', $this->html($asset));
    }

    public function test_the_german_list_says_websites_as_statamic_does(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $de = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->page(['title' => 'Home'], 'en', $de);
        app()->setLocale('de');

        $this->assertStringContainsString('alle Websites', $this->html($asset));
        $this->assertSame('Alle Websites', __('All sites'), "Statamic's own wording");
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

    public function test_the_editor_lists_the_opened_asset_not_one_its_own_fields_reference(): void
    {
        $this->blueprint('assets/images', [
            'alt' => ['type' => 'text'],
            'poster' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1],
        ]);
        $this->asset('images', 'poster.jpg');
        $video = $this->asset('images', 'video.jpg');
        $video->set('poster', 'poster.jpg')->save();
        $this->page(['title' => 'UsesVideo', 'image' => 'video.jpg']);
        $this->page(['title' => 'UsesPoster', 'image' => 'poster.jpg']);

        $shown = $this->getJson(cp_route('assets.show', base64_encode($video->id())))->assertOk()->json('data');
        $field = collect($shown['blueprint']['tabs'])->flatMap(fn ($tab) => $tab['sections'])->flatMap(fn ($section) => $section['fields'])
            ->firstWhere('handle', self::FIELD);

        $this->assertStringContainsString('UsesVideo', $field['html']);
        $this->assertStringNotContainsString('UsesPoster', $field['html']);
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
