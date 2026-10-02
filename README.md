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

## Files

Each global block is a hidden Kirby page below `content/_global/<uuid>` and owns its uploaded files:

```text
content/_global/<uuid>/
├── global-block.txt
├── image.jpg
├── image.jpg.txt
└── _changes/global-block.txt
```

A file field without an `uploads.parent` option uploads to this global block by default.

Kirby's native upload configuration can change the target for each file field:

```yaml
uploads:
  parent: site.find('page://images')
```

The native `query` and `uploads.template` options continue to work.

## Locking

Global blocks use Kirby's native changes versions and content locks. A global block that is edited on one page is locked on every other page. The host page Save and Discard actions publish or discard the global block changes made in that Panel view.

Deleting a placement removes only the local reference. Deleting a global block is blocked while a latest or changes version still references it.

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
