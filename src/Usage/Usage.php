<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

/**
 * One place an asset is used: an entry (with all its localisations), a term, a global set,
 * a user or a navigation.
 */
final readonly class Usage
{
    /**
     * @param  string  $id  the entry's root id, the term's id, the global set's or the navigation's handle, the user's id
     * @param  list<string>  $sites  the handles of the sites it is used on; none for a user
     * @param  bool  $cleaned  whether Statamic removes the reference when the asset is deleted
     * @param  string  $title  the item's title when the map was built (the root entry's, the set's, the user's name)
     * @param  string|null  $in  the handle of the entry's collection or the term's taxonomy
     * @param  array<string, array{id: string, title: string}>  $versions  an entry's localisations that use the asset, by site
     */
    public function __construct(
        public Kind $kind,
        public string $id,
        public array $sites,
        public bool $cleaned,
        public string $title = '',
        public ?string $in = null,
        public array $versions = [],
    ) {}

    /** @return array{kind: string, id: string, sites: list<string>, cleaned: bool, title: string, in: string|null, versions?: array<string, array{id: string, title: string}>} */
    public function toArray(): array
    {
        $usage = [
            'kind' => $this->kind->value, 'id' => $this->id, 'sites' => $this->sites, 'cleaned' => $this->cleaned,
            'title' => $this->title, 'in' => $this->in,
        ];

        return $this->versions === [] ? $usage : $usage + ['versions' => $this->versions];
    }

    /** @param  array{kind: string, id: string, sites: list<string>, cleaned: bool, title?: string, in?: string|null, versions?: array<string, array{id: string, title: string}>}  $usage */
    public static function fromArray(array $usage): self
    {
        return new self(Kind::from($usage['kind']), $usage['id'], $usage['sites'], $usage['cleaned'], $usage['title'] ?? '', $usage['in'] ?? null, $usage['versions'] ?? []);
    }
}
