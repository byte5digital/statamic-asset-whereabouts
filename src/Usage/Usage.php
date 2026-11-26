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
     */
    public function __construct(
        public Kind $kind,
        public string $id,
        public array $sites,
        public bool $cleaned,
    ) {}

    /** @return array{kind: string, id: string, sites: list<string>, cleaned: bool} */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'id' => $this->id, 'sites' => $this->sites, 'cleaned' => $this->cleaned];
    }

    /** @param  array{kind: string, id: string, sites: list<string>, cleaned: bool}  $usage */
    public static function fromArray(array $usage): self
    {
        return new self(Kind::from($usage['kind']), $usage['id'], $usage['sites'], $usage['cleaned']);
    }
}
