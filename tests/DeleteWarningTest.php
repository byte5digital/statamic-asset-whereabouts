<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Actions\Delete;
use Byte5\Whereabouts\Usage\MapBuilder;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Actions\Delete as StatamicDelete;
use Statamic\Assets\AssetFolder;
use Statamic\Facades\Action;
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

        $this->assertSame(
            'This asset is used in 1 place: Pages › Start. Deleting it removes it from there.',
            $this->warning([$asset]),
        );
    }

    public function test_several_places_are_named(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'One', 'image' => 'a.jpg']);
        $this->page(['title' => 'Two', 'image' => 'a.jpg']);

        $this->assertSame(
            'This asset is used in 2 places: Pages › One, Pages › Two. Deleting it removes it from them.',
            $this->warning([$asset]),
        );
    }

    public function test_more_than_five_places_are_counted(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        foreach (range(1, 7) as $number) {
            $this->page(['title' => "Page {$number}", 'image' => 'a.jpg']);
        }

        $warning = $this->warning([$asset]);

        $this->assertStringContainsString('7 places', $warning);
        $this->assertSame(5, substr_count($warning, 'Pages › '));
        $this->assertStringContainsString('and 2 more', $warning);
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

    public function test_a_title_with_a_pipe_does_not_break_the_text(): void
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'A | B', 'image' => 'a.jpg']);

        $warning = $this->warning([$asset]);

        $this->assertStringContainsString('A / B', $warning);
        $this->assertStringNotContainsString('|', $warning);
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
