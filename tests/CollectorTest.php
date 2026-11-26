<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Usage\Collector;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;

class CollectorTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function references(array $data): array
    {
        $ids = array_keys(Collector::item(clone $this->page($data))->references());
        sort($ids);

        return $ids;
    }

    public function test_an_assets_field_with_one_file(): void
    {
        $this->assertSame(['images::a.jpg'], $this->references(['image' => 'a.jpg']));
    }

    public function test_an_assets_field_with_several(): void
    {
        $this->assertSame(['images::a.jpg', 'images::sub/b.jpg'], $this->references(['gallery' => ['a.jpg', 'sub/b.jpg']]));
    }

    public function test_the_container_is_the_fields(): void
    {
        $this->assertSame(['files::a.jpg'], $this->references(['document' => 'a.jpg']));
    }

    public function test_a_link_field(): void
    {
        $this->assertSame(['images::a.jpg'], $this->references(['target' => 'asset::images::a.jpg']));
    }

    public function test_a_path_with_a_space_is_read_whole(): void
    {
        $this->assertSame(['images::my file (1).jpg'], $this->references(['target' => 'asset::images::my file (1).jpg']));
        $this->assertSame(['images::my file (1).jpg'], $this->references(['body' => [
            ['type' => 'image', 'attrs' => ['src' => 'asset::images::my file (1).jpg']],
        ]]));
    }

    public function test_a_link_to_an_entry_is_no_reference(): void
    {
        $this->assertSame([], $this->references(['target' => 'entry::123']));
    }

    public function test_markdown(): void
    {
        $this->assertSame(['images::a.jpg', 'images::b.jpg'], $this->references([
            'notes' => 'See [it](statamic://asset::images::a.jpg) and ![x](statamic://asset::images::b.jpg)',
        ]));
    }

    public function test_a_path_in_text_is_read_as_statamic_replaces_it(): void
    {
        $this->assertSame(['images::my file.jpg', "images::o'brien.jpg"], $this->references([
            'notes' => "A [file](statamic://asset::images::my file.jpg), an ![image](statamic://asset::images::o'brien.jpg).",
        ]));
        $this->assertSame(['images::plain.jpg'], $this->references([
            'notes' => 'See <a href="statamic://asset::images::plain.jpg">this</a>, then go on.',
        ]));
    }

    public function test_a_bard_image_and_link(): void
    {
        $this->assertSame(['images::a.jpg', 'images::b.jpg'], $this->references(['body' => [
            ['type' => 'image', 'attrs' => ['src' => 'asset::images::a.jpg', 'alt' => null]],
            ['type' => 'paragraph', 'content' => [[
                'type' => 'text', 'text' => 'here',
                'marks' => [['type' => 'link', 'attrs' => ['href' => 'statamic://asset::images::b.jpg']]],
            ]]],
        ]]));
    }

    public function test_a_bard_set(): void
    {
        $this->assertSame(['images::a.jpg'], $this->references(['body' => [
            ['type' => 'set', 'attrs' => ['id' => 'x1', 'values' => ['type' => 'teaser', 'picture' => 'a.jpg']]],
        ]]));
    }

    public function test_a_replicator_grid_and_group(): void
    {
        $this->assertSame(['images::a.jpg', 'images::b.jpg', 'images::c.jpg'], $this->references(['blocks' => [[
            'id' => 'r1', 'type' => 'card',
            'picture' => 'a.jpg',
            'rows' => [['id' => 'g1', 'icon' => 'b.jpg']],
            'meta' => ['cover' => 'c.jpg'],
        ]]]));
    }

    public function test_nothing_referenced(): void
    {
        $this->assertSame([], $this->references(['title' => 'a.jpg']));
    }

    public function test_the_item_is_not_changed(): void
    {
        $data = ['image' => 'a.jpg', 'body' => [['type' => 'image', 'attrs' => ['src' => 'asset::images::a.jpg']]]];
        $page = $this->page($data);
        $copy = clone $page;

        Collector::item($copy)->references();

        $this->assertSame($data, $copy->data()->all());
        $this->assertSame($data, Entry::find($page->id())->data()->all());
    }

    public function test_an_assets_field_without_a_container_uses_the_only_one(): void
    {
        AssetContainer::find('files')->delete();
        $this->blueprint('collections/pages/page', $this->pageFields() + ['any' => ['type' => 'assets']]);

        $this->assertSame(['images::a.jpg'], $this->references(['any' => 'a.jpg']));
    }
}
