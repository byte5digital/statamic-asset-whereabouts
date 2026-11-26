# Whereabouts

A Statamic 6 addon that tells editors where an asset is used, and warns them before they delete one that is.

- **"Used in" in the asset editor.** A tree of where the asset is used: a root with the number of all places,
  one branch per collection, taxonomy or kind of content with its own number, and under it every entry, term, global set, user or navigation that
  references the asset, each linked to its edit page, with the sites it is used on.
- **A warning in the delete dialog.** Deleting an asset that is in use says so, with the number of places and,
  for a single asset, the same tree with links - from the asset editor or a row's menu. A selection of several
  assets says how many of them are in use. Deleting stays possible.

No JavaScript, no control panel build, no database table, nothing written to disk.

## Installation

The package is not on Packagist. Install it from its GitHub repository:

```bash
composer config repositories.whereabouts vcs https://github.com/byte5digital/whereabouts
composer require byte5/whereabouts:dev-main
```

There is nothing to set up: no blueprint is edited, no command to run, no assets to publish.

Requires Statamic 6.44 or newer and PHP 8.2 or newer.

## What counts as used

A reference in a field of a blueprint, at any depth - inside replicators, grids, groups and Bard sets:

| Field | What it stores |
|---|---|
| Assets | the asset's path, in the field's container |
| Link | `asset::container::path` |
| Bard | image nodes and link marks; `statamic://asset::…` when Bard is saved as HTML |
| Markdown | `statamic://asset::container::path` |

Whereabouts walks the content with Statamic's own reference updater - the code that removes an asset from your
content when you delete it - and reads where that code would write. So the places it lists are the places
Statamic is about to change. On top of that:

- **Drafts count.** An entry's working copy that alone holds the asset is a usage.
- **Inherited fields count.** A localisation that takes a field from its origin is listed under its site.
- **Navigation items count,** although Statamic does not clean them: an asset deleted there leaves a dead
  reference. The list marks these with "stays after a delete".

## What it does not see

- Assets named in templates (Antlers, Blade) or PHP.
- Glide URLs and paths typed into plain text fields.
- Data outside Statamic's content: your own tables, another addon's storage.
- Fieldtypes of other addons that keep asset references in a format of their own.

An asset shown as unused can still be in use in one of these places.

## How it works

One pass over all content builds a map of asset → usages. The map is kept in the application's cache and
forgotten whenever an entry, term, global set, user, navigation tree, asset or revision is saved or deleted; the
next question builds it again. The asset browser asks for every row of a page, so within a request the map is
read once and the delete dialog - its sentence and its tree - loads no content at all: the map carries each
usage's titles and ids. Links open in a new tab.

The cache entry holds arrays and strings only. On a host where web and queue instances share no disk, use a
shared cache store (Redis, database) - the default file store would give each instance its own map.

## Configuration

```bash
php artisan vendor:publish --tag=whereabouts-config
```

`config/whereabouts.php`:

```php
// The asset containers the addon acts on; null means every container.
'containers' => [
    'images',
    'files' => ['branches' => 'collapsed', 'delete' => 'permission'],
],
```

- A container left out gets neither the field nor the warning.
- `delete` decides who may delete an asset of that container while it is in use:
  - `warn` (the default): everybody who may delete assets, after the warning.
  - `permission`: only roles with the **Delete assets that are in use** permission (group "Whereabouts" in the
    role editor) and super users. Everybody else still sees the dialog with the places, but the sentence says
    the asset can't be deleted and pressing "Delete" is refused with that message.
  - `block`: nobody, super users included.

  A selection that holds one such asset is refused as a whole, and so is a folder that holds one - nothing of
  it is deleted. The rule is the dialog's: code that deletes an asset (a replace that drops the original, a
  command) is not held back.
- `branches` decides how the branches of the "Used in" tree start for that container's assets: `open` (the
  default, also when the option is missing) or `collapsed`, which shows only the headings with their counts.

## Languages

English, German, French and Italian; the control panel shows the editor's.

## Development

```bash
composer install
composer check    # Pint, PHPStan, Rector (dry run), PHPUnit
```

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
