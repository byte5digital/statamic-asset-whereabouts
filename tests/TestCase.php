<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\ServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

/**
 * A Statamic with two sites, German and English, two asset containers on fake disks and a
 * pages collection whose blueprint holds every field type that can reference an asset, at
 * the top level and nested. Content lives in the Stache and is never written to disk.
 */
abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected string $blueprints;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.multisite', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'de' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/'],
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/en/'],
        ]);

        foreach (['images', 'files'] as $handle) {
            Storage::fake($handle);
            AssetContainer::make($handle)->disk($handle)->title(Str::title($handle))->save();
        }

        $this->blueprints = sys_get_temp_dir().'/whereabouts-tests-'.Str::random(8);
        Blueprint::setDirectory($this->blueprints);
        $this->blueprint('collections/pages/page', $this->pageFields());

        Collection::make('pages')->title('Pages')->sites(['de', 'en'])->save();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->blueprints);

        parent::tearDown();
    }

    /**
     * Writes a blueprint file of one tab and one section.
     *
     * @param  array<string, array<string, mixed>>  $fields  handle => field config
     */
    protected function blueprint(string $path, array $fields): void
    {
        $file = $this->blueprints.'/'.$path.'.yaml';
        (new Filesystem)->ensureDirectoryExists(dirname($file));
        file_put_contents($file, YAML::dump(['tabs' => ['main' => ['sections' => [['fields' => $this->fieldList($fields)]]]]]));
        Blink::flush();
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<array{handle: string, field: array<string, mixed>}>
     */
    protected function fieldList(array $fields): array
    {
        return collect($fields)->map(fn (array $field, string $handle) => ['handle' => $handle, 'field' => $field])->values()->all();
    }

    /** @return array<string, array<string, mixed>> */
    protected function pageFields(): array
    {
        $images = ['type' => 'assets', 'container' => 'images'];

        return [
            'title' => ['type' => 'text'],
            'image' => $images + ['max_files' => 1],
            'gallery' => $images,
            'document' => ['type' => 'assets', 'container' => 'files'],
            'target' => ['type' => 'link', 'container' => 'images'],
            'notes' => ['type' => 'markdown', 'container' => 'images'],
            'body' => ['type' => 'bard', 'container' => 'images', 'sets' => ['main' => ['sets' => [
                'teaser' => ['fields' => $this->fieldList(['picture' => $images])],
            ]]]],
            'blocks' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
                'card' => ['fields' => $this->fieldList([
                    'picture' => $images,
                    'rows' => ['type' => 'grid', 'fields' => $this->fieldList(['icon' => $images])],
                    'meta' => ['type' => 'group', 'fields' => $this->fieldList(['cover' => $images])],
                ])],
            ]]]],
        ];
    }

    protected function asset(string $container, string $path): AssetContract
    {
        Storage::disk($container)->put($path, 'x');
        $asset = Asset::make()->container($container)->path($path);
        $asset->save();

        return $asset;
    }

    /** @param  array<string, mixed>  $data */
    protected function page(array $data, string $site = 'de', ?EntryContract $origin = null, bool $published = true): EntryContract
    {
        $entry = Entry::make()->id((string) Str::uuid())->collection('pages')->blueprint('page')->locale($site)
            ->slug(Str::random(8))->published($published)->data($data);

        if ($origin instanceof EntryContract) {
            $entry->origin($origin);
        }

        $entry->save();

        return $entry;
    }
}
