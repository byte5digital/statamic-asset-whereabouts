<?php

declare(strict_types=1);

namespace Byte5\Whereabouts;

use Byte5\Whereabouts\Actions\Delete;
use Byte5\Whereabouts\Fieldtypes\UsedIn;
use Byte5\Whereabouts\Listeners\AddUsedInField;
use Byte5\Whereabouts\Listeners\ForgetUsageMap;
use Byte5\Whereabouts\Usage\UsageMap;
use Statamic\Events\AssetContainerBlueprintFound;
use Statamic\Events\AssetDeleted;
use Statamic\Events\AssetSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\GlobalVariablesDeleted;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Events\NavTreeDeleted;
use Statamic\Events\NavTreeSaved;
use Statamic\Events\RevisionDeleted;
use Statamic\Events\RevisionSaved;
use Statamic\Events\TermDeleted;
use Statamic\Events\TermSaved;
use Statamic\Events\UserDeleted;
use Statamic\Events\UserSaved;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [UsedIn::class];

    // Whatever can add, move or remove a reference to an asset forgets the usage map.
    protected $listen = [
        AssetContainerBlueprintFound::class => [AddUsedInField::class],
        EntrySaved::class => [ForgetUsageMap::class],
        EntryDeleted::class => [ForgetUsageMap::class],
        TermSaved::class => [ForgetUsageMap::class],
        TermDeleted::class => [ForgetUsageMap::class],
        GlobalVariablesSaved::class => [ForgetUsageMap::class],
        GlobalVariablesDeleted::class => [ForgetUsageMap::class],
        UserSaved::class => [ForgetUsageMap::class],
        UserDeleted::class => [ForgetUsageMap::class],
        NavTreeSaved::class => [ForgetUsageMap::class],
        NavTreeDeleted::class => [ForgetUsageMap::class],
        AssetSaved::class => [ForgetUsageMap::class],
        AssetDeleted::class => [ForgetUsageMap::class],
        RevisionSaved::class => [ForgetUsageMap::class],
        RevisionDeleted::class => [ForgetUsageMap::class],
    ];

    public function register(): void
    {
        parent::register();

        // Statamic resolves its actions through the container: ours takes the delete action's place.
        $this->app->bind(\Statamic\Actions\Delete::class, Delete::class);

        // One per request: the asset browser asks for every row of a page.
        $this->app->singleton(UsageMap::class);
    }
}
