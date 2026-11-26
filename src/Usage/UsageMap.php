<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Byte5\Whereabouts\Settings;
use Illuminate\Support\Facades\Cache;
use Statamic\Contracts\Assets\Asset;

/**
 * Where every asset is used, kept between requests.
 *
 * The map is built by one pass over all content (MapBuilder) and stored in the application's
 * cache as plain arrays until some content changes (Listeners\ForgetUsageMap). The asset
 * browser asks for every row of a page, so within a request it is read from the cache once.
 */
class UsageMap
{
    public const CACHE_KEY = 'whereabouts.usage-map';

    /** A backstop: content events forget the map long before. */
    private const SECONDS = 86400;

    /** @var array<string, list<array{kind: string, id: string, sites: list<string>, cleaned: bool, title: string, in: string|null}>>|null */
    private ?array $map = null;

    public function __construct(private readonly MapBuilder $builder) {}

    /** @return list<Usage> */
    public function usagesOf(Asset $asset): array
    {
        if (! Settings::includes((string) $asset->containerHandle())) {
            return [];
        }

        return array_map(Usage::fromArray(...), $this->map()[(string) $asset->id()] ?? []);
    }

    /**
     * @param  iterable<Asset>  $assets
     * @return array<string, list<Usage>> by asset id; only the assets in use
     */
    public function usagesOfMany(iterable $assets): array
    {
        $usages = [];

        foreach ($assets as $asset) {
            if (($found = $this->usagesOf($asset)) !== []) {
                $usages[(string) $asset->id()] = $found;
            }
        }

        return $usages;
    }

    public function forget(): void
    {
        $this->map = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, list<array{kind: string, id: string, sites: list<string>, cleaned: bool, title: string, in: string|null}>> */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (! is_array($cached)) {
            $cached = $this->builder->build();
            Cache::put(self::CACHE_KEY, $cached, self::SECONDS);
        }

        return $this->map = $cached;
    }
}
