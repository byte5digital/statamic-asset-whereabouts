<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Data\DataReferenceUpdater;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Assets\Assets;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Link;
use Statamic\Fieldtypes\Markdown;

/**
 * The assets one item references in its own data.
 *
 * Statamic's reference updater knows how to reach every field of an item, through replicators,
 * grids, groups and Bard sets (each fieldtype's iterateReferenceFields()). This is that walk with
 * the replacing taken out: where the updater rewrites a value, the collector reads which assets
 * it names. It never changes or saves the item.
 */
class Collector extends DataReferenceUpdater
{
    /**
     * A link in text, as Statamic's own replacing finds it (UpdatesReferences::replaceStatamicUrls()):
     * statamic://asset::container::path between a parenthesis or quote and the next one.
     */
    private const IN_TEXT = '/[("]statamic:\/\/asset::([^()":]+)::([^)"]*)[)"]/i';

    /** @var array<string, true> */
    private array $found = [];

    /**
     * @return array<string, true> asset ids ("container::path")
     */
    public function references(): array
    {
        $this->found = [];

        $this->recursivelyUpdateFields($this->getTopLevelFields());

        return $this->found;
    }

    /**
     * Statamic documents a Fields object here and hands in a collection of fields; both are taken.
     *
     * @param  Collection<array-key, Field>|Fields  $fields
     * @param  string|null  $dottedPrefix
     */
    protected function recursivelyUpdateFields($fields, $dottedPrefix = null): void
    {
        $fields = $fields instanceof Fields ? $fields->all() : $fields;
        $data = $this->item->data()->all();

        $this->fieldsWithReferenceUpdates($fields)->each(function (Field $field) use ($data, $dottedPrefix): void {
            $value = Arr::get($data, $dottedPrefix.$field->handle());

            if ($value !== null) {
                $this->read($field, $value);
            }
        });

        $this->updateNestedFieldValues($fields, $dottedPrefix);
    }

    private function read(Field $field, mixed $value): void
    {
        $fieldtype = $field->fieldtype();

        match (true) {
            $fieldtype instanceof Assets => $this->readPaths($this->containerOf($field), $value),
            $fieldtype instanceof Link => $this->readWhole($value),
            $fieldtype instanceof Bard => is_string($value) ? $this->readText($value) : $this->readBard($value),
            $fieldtype instanceof Markdown => is_string($value) ? $this->readText($value) : null,
            default => null,
        };
    }

    /** An assets field stores a path, or a list of them, relative to its container. */
    private function readPaths(?string $container, mixed $value): void
    {
        if ($container === null) {
            return;
        }

        foreach (Arr::wrap($value) as $path) {
            if (is_string($path) && $path !== '') {
                $this->found[$container.'::'.$path] = true;
            }
        }
    }

    /** The field's container, or the site's only one where the field names none. */
    private function containerOf(Field $field): ?string
    {
        $container = $field->get('container');

        if (is_string($container) && $container !== '') {
            return $container;
        }

        $containers = AssetContainer::all();

        return $containers->count() === 1 ? $containers->first()->handle() : null;
    }

    /** A value that is one reference and nothing else: "asset::container::path", the path taken whole. */
    private function readWhole(mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $parts = explode('::', (string) preg_replace('#^statamic://#', '', $value), 3);

        if (count($parts) === 3 && $parts[0] === 'asset' && $parts[1] !== '' && $parts[2] !== '') {
            $this->found[$parts[1].'::'.$parts[2]] = true;
        }
    }

    /** Every link to an asset in Markdown or in Bard saved as HTML. */
    private function readText(string $value): void
    {
        if (! str_contains($value, 'asset::')) {
            return;
        }

        preg_match_all(self::IN_TEXT, $value, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $container, $path]) {
            if ($path !== '') {
                $this->found[$container.'::'.$path] = true;
            }
        }
    }

    /** A Bard document: image nodes name their asset in attrs.src, link marks in attrs.href. */
    private function readBard(mixed $nodes): void
    {
        if (! is_array($nodes)) {
            return;
        }

        $type = $nodes['type'] ?? null;
        $attribute = match ($type) {
            'image' => 'src',
            'link' => 'href',
            default => null,
        };
        $reference = $attribute === null ? null : ($nodes['attrs'][$attribute] ?? null);

        $this->readWhole($reference);

        foreach ($nodes as $child) {
            $this->readBard($child);
        }
    }
}
