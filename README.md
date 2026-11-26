# Whereabouts

A Statamic 6 addon that tells editors where an asset is used, and warns them before they delete one that is.

- **"Used in" in the asset editor.** Every entry, term, global set, user and navigation that references the asset,
  each linked to its edit page, with the sites it is used on.
- **A warning in the delete dialog.** Deleting an asset that is in use says so and names the places - from the
  asset editor, a row's menu or a selection of several assets. Deleting stays possible.

No JavaScript, no control panel build, no database table, nothing written to disk.

## Installation

The package is not published yet. Install it from a local checkout with a path repository:

```bash
composer config repositories.whereabouts path ../byte5-whereabouts
composer require byte5/whereabouts:@dev
```

There is nothing to set up: no blueprint is edited, no command to run.

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
read once and the delete warning loads no content at all.

The cache entry holds arrays and strings only. On a host where web and queue instances share no disk, use a
shared cache store (Redis, database) - the default file store would give each instance its own map.

## Configuration

```bash
php artisan vendor:publish --tag=whereabouts-config
```

`config/whereabouts.php`:

```php
// The asset containers the addon acts on, by handle; null means every container.
'containers' => null,
```

A container left out gets neither the field nor the warning.

## Languages

English, German, French and Italian; the control panel shows the editor's.

## Development

```bash
composer install
composer check    # Pint, PHPStan, Rector (dry run), PHPUnit
```
