# Kirby Global Blocks

Reusable global blocks for Kirby 5 blocks and layout fields.

**Supports Kirby 5.5 and newer (Kirby 5).**

## Requirements

- Kirby 5.5 or newer, below Kirby 6
- PHP 8.3 or newer

## Field blueprint

Enable global blocks on a normal field:

```yaml
type: blocks
globalBlocks: true
```

The same option works on `type: layout` fields.

The field keeps its normal fieldsets. The block selector only shows global blocks whose block type is valid for the field.

Global blocks use the block types of the `fieldsets` option of this plugin (see [Options](#options)), not the fieldsets of the host field. Types are matched by name. A host field that defines a block type inline or changes it with `extends` can only turn blocks into global blocks if a block type with the same name exists for global blocks, and the global block uses that definition.

Global blocks also work in blocks fields that are nested in structure fields, object fields or other blocks.

## Stored references

The page stores a placement UUID and the global block UUID. It does not store a copy of the global content:

```json
{
	"id": "placement-uuid",
	"type": "global",
	"content": {
		"uuid": "global-block-uuid"
	}
}
```

`toBlocks()` and `toLayouts()` replace this reference with the current global block content. The resolved block keeps the placement UUID.

After a global block was edited on a page, the reference also contains a `revision`. It marks the page as changed, so that the page Save action publishes the global block.

Other code that reads the raw field value, for example `$site->search()`, only sees the reference.

## Files

Each global block is a hidden Kirby page below `content/_global/<uuid>` and owns its uploaded files:

```text
content/_global/<uuid>/
├── global-block.txt
├── image.jpg
├── image.jpg.txt
└── _changes/global-block.txt
```

A file field without an `uploads.parent` option uploads to this global block by default. When a block that uses files of its page is turned into a global block, the dialog asks whether to copy these files to the global block. Without a copy, the global block keeps the references to the files of the page. Deleting that page or the files then removes them from the global block on all pages.

File fields must store UUIDs (the Kirby default). The plugin adds the global blocks and their files to Kirby's UUID cache, because the site index does not contain them. Do not disable the `uuid` cache.

Kirby's native upload configuration can change the target for each file field:

```yaml
uploads:
  parent: site.find('page://images')
```

The native `query` and `uploads.template` options continue to work.

## Preview

The Panel shows previews of global blocks. The plugin ships a basic preview template. Create `site/templates/global-block-preview.php` to render the block with the styles of your site:

```php
<?php snippet('header') ?>
<?= $block ?>
<?php snippet('footer') ?>
```

The template gets the `$block` and the global block as `$page`. Global blocks do not have public URLs. The preview URL needs a Panel user with the `access` permission or a preview token.

## Locking

Global blocks use Kirby's native changes versions and content locks. A global block that is edited on one page is locked on every other page. The host page Save and Discard actions publish or discard the global block changes made in that Panel view.

Deleting a placement removes only the local reference. Deleting a global block is blocked while a latest or changes version of the site, a page, a user, a file or another global block still references it. The delete dialog lists these places.

A global block that was created with "Turn into global block" is deleted again when the page changes are discarded and nothing else uses it.

## Permissions

Configure permissions in a user blueprint:

```yaml
permissions:
  tobimori.global-blocks:
    access: true
    create: true
    update: true
    delete: false
```

Global blocks are pages, so the page permissions of the role also apply. For example, a role with `pages.update: false` cannot change global blocks.

Without `access`, the Global blocks area is hidden, but the user can still add existing global blocks on pages. Without `update`, global blocks are read-only on pages. Without `create`, "Turn into global block" is not available.

## Options

```php
return [
    'tobimori.global-blocks' => [
        // Optional storage root
        'root' => null,

        // Optional global block type allowlist, supports groups like the blocks field
        'fieldsets' => null,

        // Block types to exclude from the central create dialog
        'fieldsetsIgnore' => [],
    ],
];
```

## Development

```bash
mise run setup
mise run dev
```
