<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint;

/**
 * What the collector reads: some data and the blueprint that describes it. A stand-in for the
 * real item, so the walk never touches an instance Statamic holds - and so the data can be what
 * a site shows (a localisation's values merged over its origin's) rather than what one row stores.
 */
final readonly class Subject
{
    /** @param  Collection<array-key, mixed>  $data */
    public function __construct(private Collection $data, private Blueprint $blueprint) {}

    /** @return Collection<array-key, mixed> */
    public function data(): Collection
    {
        return $this->data;
    }

    public function blueprint(): Blueprint
    {
        return $this->blueprint;
    }
}
