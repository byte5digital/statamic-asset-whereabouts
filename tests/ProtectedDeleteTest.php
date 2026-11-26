<?php

namespace Byte5\Whereabouts\Tests;

use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Action;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\FakesRoles;

/**
 * Who may delete an asset that is in use: everybody who may delete assets (the default), only
 * users with the "delete assets in use" permission, or nobody - as the container's setting says.
 */
class ProtectedDeleteTest extends TestCase
{
    use FakesRoles;

    private const REFUSED_ONE = 'This asset is used in 1 place and can\'t be deleted. Remove it from there first.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['whereabouts.containers' => ['images' => ['delete' => 'permission'], 'files']]);
    }

    /** An editor who may do everything with the images - and, where asked, delete those in use. */
    private function actAsEditor(bool $mayDeleteUsed = false): void
    {
        $this->setTestRoles(['editor' => array_merge([
            'access cp', 'view images assets', 'upload images assets', 'edit images assets', 'delete images assets', 'edit images folders',
            'view files assets', 'edit files assets', 'delete files assets',
        ], $mayDeleteUsed ? ['delete assets in use'] : [])]);

        $user = User::make()->email('editor@example.com')->assignRole('editor');
        $user->save();
        $this->actingAs($user);
    }

    private function actAsSuper(): void
    {
        $user = User::make()->email('admin@example.com')->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    private function usedImage(): AssetContract
    {
        $asset = $this->asset('images', 'a.jpg');
        $this->page(['title' => 'Start', 'image' => 'a.jpg']);

        return $asset;
    }

    /** @param  list<string>  $ids */
    private function runDelete(array $ids): array
    {
        return $this->postJson(cp_route('assets.actions.run'), [
            'action' => 'delete', 'selections' => $ids, 'context' => ['container' => 'images'], 'values' => [],
        ])->assertOk()->json();
    }

    private function warning(AssetContract $asset): ?string
    {
        return Action::for($asset)->first(fn ($action) => $action::handle() === 'delete')->toArray()['warningText'];
    }

    public function test_the_permission_is_offered_to_roles(): void
    {
        // As the control panel's middleware does before a role is edited.
        $permission = Permission::boot()->get('delete assets in use');

        $this->assertNotNull($permission);
        $this->assertSame('Delete assets that are in use', $permission->label());
        $this->assertSame('whereabouts', $permission->group());
    }

    public function test_an_editor_without_the_permission_is_refused_and_told_why(): void
    {
        $this->actAsEditor();
        $asset = $this->usedImage();

        $this->assertSame(self::REFUSED_ONE, $this->warning($asset));

        $response = $this->runDelete([$asset->id()]);

        $this->assertFalse($response['success']);
        $this->assertSame(self::REFUSED_ONE, $response['message']);
        $this->assertNotNull(Asset::find('images::a.jpg'));
        $this->assertTrue($asset->disk()->exists('a.jpg'));
    }

    public function test_the_refused_editor_still_sees_the_places(): void
    {
        $this->actAsEditor();

        $fields = collect(Action::for($this->usedImage())->first(fn ($action) => $action::handle() === 'delete')->toArray()['fields'])->keyBy('handle');

        $this->assertStringContainsString('Start', $fields['whereabouts_usages']['html']);
    }

    public function test_an_editor_with_the_permission_deletes_after_the_warning(): void
    {
        $this->actAsEditor(mayDeleteUsed: true);
        $asset = $this->usedImage();

        $this->assertSame('This asset is used in 1 place. Deleting it removes it from there.', $this->warning($asset));
        $this->assertTrue($this->runDelete([$asset->id()])['success']);
        $this->assertNull(Asset::find('images::a.jpg'));
    }

    public function test_a_super_user_deletes(): void
    {
        $this->actAsSuper();
        $asset = $this->usedImage();

        $this->assertTrue($this->runDelete([$asset->id()])['success']);
        $this->assertNull(Asset::find('images::a.jpg'));
    }

    public function test_an_asset_that_is_not_in_use_is_deleted_as_ever(): void
    {
        $this->actAsEditor();
        $asset = $this->asset('images', 'unused.jpg');

        $this->assertNull($this->warning($asset));
        $this->assertTrue($this->runDelete([$asset->id()])['success']);
        $this->assertNull(Asset::find('images::unused.jpg'));
    }

    public function test_a_selection_with_an_asset_in_use_is_refused_as_a_whole(): void
    {
        $this->actAsEditor();
        $used = $this->usedImage();
        $unused = $this->asset('images', 'unused.jpg');

        $bulk = Action::forBulk(collect([$used, $unused]))->first(fn ($action) => $action::handle() === 'delete')->toArray()['warningText'];
        $this->assertSame('1 of the 2 selected assets are in use and can\'t be deleted. Remove them from the content that uses them first.', $bulk);

        $response = $this->runDelete([$used->id(), $unused->id()]);

        $this->assertFalse($response['success']);
        $this->assertNotNull(Asset::find('images::a.jpg'));
        $this->assertNotNull(Asset::find('images::unused.jpg'), 'nothing of the selection goes');
    }

    public function test_a_folder_holding_an_asset_in_use_is_refused_and_stays_whole(): void
    {
        $this->actAsEditor();
        $this->asset('images', 'sub/a.jpg');
        $this->asset('images', 'sub/deep/b.jpg');
        $this->page(['title' => 'Start', 'image' => 'sub/deep/b.jpg']);

        $response = $this->postJson(cp_route('assets.folders.actions.run', 'images'), [
            'action' => 'delete', 'selections' => ['sub'], 'context' => ['container' => 'images'], 'values' => [],
        ])->assertOk()->json();

        $this->assertFalse($response['success']);
        $this->assertSame('This folder holds 1 asset that is in use and can\'t be deleted. Remove it from the content that uses it first.', $response['message']);
        $disk = AssetContainer::find('images')->disk();
        $this->assertTrue($disk->exists('sub/a.jpg'));
        $this->assertTrue($disk->exists('sub/deep/b.jpg'));
        $this->assertNotNull(Asset::find('images::sub/a.jpg'));
    }

    public function test_a_folder_without_assets_in_use_is_deleted(): void
    {
        $this->actAsEditor();
        $this->asset('images', 'sub/a.jpg');

        $response = $this->postJson(cp_route('assets.folders.actions.run', 'images'), [
            'action' => 'delete', 'selections' => ['sub'], 'context' => ['container' => 'images'], 'values' => [],
        ])->assertOk()->json();

        $this->assertTrue($response['success']);
        $this->assertNull(Asset::find('images::sub/a.jpg'));
    }

    public function test_a_container_that_only_warns_lets_the_editor_delete(): void
    {
        config(['whereabouts.containers' => ['images']]);
        $this->actAsEditor();
        $asset = $this->usedImage();

        $this->assertTrue($this->runDelete([$asset->id()])['success']);
        $this->assertNull(Asset::find('images::a.jpg'));
    }

    public function test_a_container_that_blocks_refuses_everybody(): void
    {
        config(['whereabouts.containers' => ['images' => ['delete' => 'block']]]);
        $this->actAsSuper();
        $asset = $this->usedImage();

        $this->assertSame(self::REFUSED_ONE, $this->warning($asset));
        $this->assertFalse($this->runDelete([$asset->id()])['success']);
        $this->assertNotNull(Asset::find('images::a.jpg'));
    }

    public function test_replacing_an_asset_in_use_and_deleting_the_original_is_not_refused(): void
    {
        $this->actAsEditor();
        $original = $this->usedImage();
        $replacement = $this->asset('images', 'new.jpg');

        $replacement->replace($original, true);

        $this->assertNull(Asset::find('images::a.jpg'), 'code that deletes an asset is not the dialog');
    }
}
