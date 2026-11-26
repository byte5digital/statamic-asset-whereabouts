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

    public function test_the_fixture_site_exists(): void
    {
        $this->assertSame(['de', 'en'], Site::all()->keys()->all());
        $this->assertNotNull($this->asset('images', 'a.jpg')->container());
        $this->assertSame('a.jpg', $this->page(['title' => 'A', 'image' => 'a.jpg'])->get('image'));
    }
}
