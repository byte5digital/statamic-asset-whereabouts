<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Actions\Delete;
use Byte5\Whereabouts\Usage\Describer;
use Byte5\Whereabouts\Usage\MapBuilder;
use Byte5\Whereabouts\Usage\Usage;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Actions\Delete as StatamicDelete;
use Statamic\Assets\AssetFolder;
use Statamic\Facades\Action;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\User;

class DeleteWarningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::make()->email('admin@example.com')->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    /** The warning as the control panel receives it for a selection. */
    private function warning(array $items): ?string
    {
        $actions = count($items) === 1 ? Action::for($items[0]) : Action::forBulk(collect($items));

        return $actions->first(fn ($action) => $action::handle() === 'delete')->toArray()['warningText'];
    }

    public function test_statamic_resolves_our_delete_action(): void
    {
        $this->assertInstanceOf(Delete::class, Action::get('delete'));
    }

    public function test_an_asset_in_use_names_the_place(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $this->assertSame('This asset is used in 1 place. Deleting it removes it from there.', $this->warning([$asset]));
        $this->assertStringContainsString('>Start</a>', $this->dialogFields($asset)['whereabouts_usages']['html']);
    }

    public function test_several_places_are_named(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'One', 'image' => 'a.jpg']);
        $this->page(['title' => 'Two', 'image' => 'a.jpg']);

        $this->assertSame(
            'This asset is used in 2 places. Deleting it removes it from them.',
            $this->warning([$asset]),
        );
    }

    /** The dialog's fields as the control panel receives them for one item. */
    private function dialogFields(object $item): array
    {
        return collect(Action::for($item)->first(fn ($action) => $action::handle() === 'delete')->toArray()['fields'])->keyBy('handle')->all();
    }

    public function test_the_dialog_lists_the_places_as_a_tree_of_links(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $one = $this->page(['title' => 'One', 'image' => 'a.jpg']);
        $this->page(['title' => 'Two', 'image' => 'a.jpg']);

        $fields = $this->dialogFields($asset);

        $this->assertSame(['whereabouts_usages'], array_keys($fields));
        $this->assertSame('html', $fields['whereabouts_usages']['type']);
        $html = $fields['whereabouts_usages']['html'];
        $this->assertStringContainsString('All places', $html);
        $this->assertStringContainsString('(2)', $html);
        $this->assertStringContainsString('<a href="'.e($one->editUrl()).'" target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('>Two</a>', $html);
    }

    public function test_an_unused_asset_a_selection_and_other_items_have_no_list(): void
    {
        $used = $this->asset('images', 'a.jpg');
        $unused = $this->asset('images', 'b.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $this->assertSame([], $this->dialogFields($unused));
        $this->assertSame([], Action::forBulk(collect([$used, $unused]))->first(fn ($action) => $action::handle() === 'delete')->toArray()['fields']);
        $this->assertSame([], app(Delete::class)->items([Collection::find('pages')])->toArray()['fields']);
    }

    public function test_the_delete_still_runs_with_the_list_in_the_dialog(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $this->postJson(cp_route('assets.actions.run'), [
            'action' => 'delete',
            'selections' => [$asset->id()],
            'context' => ['container' => 'images'],
            'values' => ['whereabouts_usages' => null],
        ])->assertOk();

        $this->assertNull(Asset::find('images::a.jpg'));
    }

    public function test_an_unused_asset_has_no_warning(): void
    {
        $this->assertNull($this->warning([$this->asset('images', 'a.jpg')]));
    }

    public function test_a_bulk_selection_counts_the_assets_in_use(): void
    {
        $assets = [$this->asset('images', 'a.jpg'), $this->asset('images', 'b.jpg'), $this->asset('images', 'c.jpg')];
        $this->page(['title' => 'Start', 'gallery' => ['a.jpg', 'b.jpg']]);

        $this->assertSame(
            '2 of the 3 selected assets are in use. Deleting them removes them from the content that uses them.',
            $this->warning($assets),
        );
    }

    public function test_a_bulk_selection_of_unused_assets_has_no_warning(): void
    {
        $this->assertNull($this->warning([$this->asset('images', 'a.jpg'), $this->asset('images', 'b.jpg')]));
    }

    public function test_a_folder_keeps_statamics_dialog(): void
    {
        $this->asset('images', 'sub/a.jpg');
        $this->page(['image' => 'sub/a.jpg']);
        $folder = (new AssetFolder)->container(AssetContainer::find('images'))->path('sub');

        $this->assertNull($this->warning([$folder]));
    }

    public function test_an_entry_keeps_statamics_dialog(): void
    {
        // On a multisite Statamic offers entries another delete action; ours is asked directly.
        $page = $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        $this->asset('images', 'a.jpg');

        $this->assertSame(
            (new StatamicDelete)->items([$page])->warningText(),
            app(Delete::class)->items([$page])->warningText(),
        );
    }

    public function test_a_collection_keeps_statamics_warning(): void
    {
        $collection = Collection::find('pages');
        $expected = (new StatamicDelete)->items([$collection])->warningText();

        $this->assertNotNull($expected);
        $this->assertSame($expected, app(Delete::class)->items([$collection])->warningText());
    }

    public function test_a_title_that_names_a_language_file_is_no_translation_key(): void
    {
        // __('Validation') is Laravel's whole validation group, an array.
        Collection::find('pages')->title('Validation')->save();
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        $this->assertStringContainsString('Validation', $this->dialogFields($asset)['whereabouts_usages']['html']);
        $this->assertStringContainsString('Validation', $asset->blueprint()->field('whereabouts_used_in')->toPublishArray()['html']);
    }

    public function test_the_dialog_loads_no_content(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);
        app(UsageMap::class)->usagesOf($asset);

        // The listing builds this text per row: it must come from the map alone.
        $this->app->bind(Describer::class, fn () => new class extends Describer
        {
            public function describe(Usage $usage): ?array
            {
                throw new \LogicException('The warning loaded an item.');
            }
        });

        $this->assertSame('This asset is used in 1 place. Deleting it removes it from there.', $this->warning([$asset]));
        $this->assertStringContainsString('>Start</a>', $this->dialogFields($asset)['whereabouts_usages']['html']);
    }

    public function test_the_listing_asks_the_map_once(): void
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

        $assets = collect(range(1, 20))->map(fn (int $number) => $this->asset('images', "{$number}.jpg"));
        $this->page(['title' => 'Start', 'image' => '3.jpg']);

        $warnings = $assets->map(fn ($asset) => $this->warning([$asset]))->filter();

        $this->assertCount(1, $warnings);
        $this->assertSame(1, $spy->builds);
    }
}
