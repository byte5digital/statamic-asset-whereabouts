<?php

namespace Byte5\Whereabouts\Tests;

use Byte5\Whereabouts\Settings;
use Statamic\Facades\Site;

class SettingsTest extends TestCase
{
    public function test_every_container_is_included_by_default(): void
    {
        $this->assertTrue(Settings::includes('images'));
    }

    public function test_only_listed_containers_are_included(): void
    {
        config(['whereabouts.containers' => ['images']]);

        $this->assertTrue(Settings::includes('images'));
        $this->assertFalse(Settings::includes('files'));
    }

    public function test_a_container_may_be_listed_with_its_own_options(): void
    {
        config(['whereabouts.containers' => ['images', 'files' => ['branches' => 'collapsed']]]);

        $this->assertTrue(Settings::includes('images'));
        $this->assertTrue(Settings::includes('files'));
        $this->assertFalse(Settings::includes('news'));
    }

    public function test_branches_start_open_unless_a_container_asks_for_collapsed(): void
    {
        $this->assertFalse(Settings::collapsed('images'), 'no list at all');

        config(['whereabouts.containers' => [
            'images',
            'files' => ['branches' => 'collapsed'],
            'news' => ['branches' => 'open'],
            'videos' => [],
            'icons' => ['branches' => 'sideways'],
        ]]);

        $this->assertFalse(Settings::collapsed('images'));
        $this->assertTrue(Settings::collapsed('files'));
        $this->assertFalse(Settings::collapsed('news'));
        $this->assertFalse(Settings::collapsed('videos'), 'not set');
        $this->assertFalse(Settings::collapsed('icons'), 'an unknown value');
        $this->assertFalse(Settings::collapsed('uploads'), 'not listed');
    }

    public function test_a_container_decides_who_may_delete_an_asset_in_use(): void
    {
        $this->assertSame('warn', Settings::deletion('images'), 'no list at all');

        config(['whereabouts.containers' => [
            'images',
            'files' => ['delete' => 'permission'],
            'news' => ['delete' => 'block'],
            'videos' => ['delete' => 'never'],
        ]]);

        $this->assertSame('warn', Settings::deletion('images'));
        $this->assertSame('permission', Settings::deletion('files'));
        $this->assertSame('block', Settings::deletion('news'));
        $this->assertSame('warn', Settings::deletion('videos'), 'an unknown value');
        $this->assertSame('warn', Settings::deletion('uploads'), 'not listed');
    }

    public function test_the_fixture_site_exists(): void
    {
        $this->assertSame(['de', 'en'], Site::all()->keys()->all());
        $this->assertNotNull($this->asset('images', 'a.jpg')->container());
        $this->assertSame('a.jpg', $this->page(['title' => 'A', 'image' => 'a.jpg'])->get('image'));
    }
}
