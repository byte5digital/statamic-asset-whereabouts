<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\MapBuilder;
use Statamic\Assets\AssetReferenceUpdater;

/**
 * What the map lists for an asset is what Statamic would change when that asset is deleted:
 * its own reference updater, run without saving on copies, is the reference.
 */
class AgreesWithStatamicTest extends TestCase
{
    public function test_the_map_names_exactly_the_entries_statamic_would_change(): void
    {
        $bard = fn (array $node): array => ['body' => [$node]];
        $pages = collect([
            ['image' => 'a.jpg'],
            ['gallery' => ['x.jpg', 'a.jpg']],
            ['target' => 'asset::images::a.jpg'],
            ['notes' => 'A [file](statamic://asset::images::a.jpg).'],
            $bard(['type' => 'image', 'attrs' => ['src' => 'asset::images::a.jpg']]),
            $bard(['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'statamic://asset::images::a.jpg']]]]]]),
            $bard(['type' => 'set', 'attrs' => ['id' => 's', 'values' => ['type' => 'teaser', 'picture' => 'a.jpg']]]),
            ['blocks' => [['id' => 'r', 'type' => 'card', 'picture' => 'a.jpg']]],
            ['blocks' => [['id' => 'r', 'type' => 'card', 'rows' => [['id' => 'g', 'icon' => 'a.jpg']]]]],
            ['blocks' => [['id' => 'r', 'type' => 'card', 'meta' => ['cover' => 'a.jpg']]]],
            // Not a reference to images::a.jpg:
            ['image' => 'b.jpg'],
            ['document' => 'a.jpg'],
            ['title' => 'a.jpg'],
        ])->map(fn (array $data) => $this->page($data));

        $fromStatamic = $pages
            ->filter(fn ($page) => AssetReferenceUpdater::item(clone $page)->filterByContainer('images')->withoutSaving()->updateReferences('a.jpg', null))
            ->map->id()->values()->all();

        $fromMap = array_column(app(MapBuilder::class)->build()['images::a.jpg'], 'id');

        $this->assertCount(10, $fromStatamic);
        $this->assertEqualsCanonicalizing($fromStatamic, $fromMap);
    }
}
