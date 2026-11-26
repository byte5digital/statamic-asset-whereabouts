<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Listeners;

use Byte5\Whereabouts\Usage\UsageMap;

/** Content changed: what the map says may no longer hold, so the next question builds it anew. */
class ForgetUsageMap
{
    public function __construct(private readonly UsageMap $map) {}

    public function handle(object $event): void
    {
        $this->map->forget();
    }
}
