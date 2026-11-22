---
title: How it works
intro: How references, saves, locks, file UUIDs, and limits behave across pages
---

In [Working with global blocks](1_guides), you edited, detached, and deleted placements across pages and in **Global blocks**. Here is how those placements stay in sync.

## References instead of copies

A placement stores its own ID and the global block's UUID:

```json
{
	"id": "placement-uuid",
	"type": "global",
	"content": {
		"uuid": "global-block-uuid"
	}
}
```

When a template calls `toBlocks()` or `toLayouts()`, each reference resolves to the published global block while keeping its placement `$block->id()`, so repeated placements keep unique IDs.

If a global block is deleted on disk, remaining placements show a missing-block notice in the Panel and render nothing. Recursive references are rejected on save and render nothing if introduced on disk.

## Save, Discard, and locks across pages

You can edit a global block in **Global blocks** (with its files section) or inline on a page, where double-clicking the colored header bar renames it and repeated placements update together.

Inline edits keep your unsaved changes until you save. Clicking **Save** publishes the page and every global block edited there. Clicking **Discard** discards both and deletes any global block converted in that session if nothing else uses it.

While you have unsaved changes, other editors cannot edit the block on any page or in **Global blocks**. They see a lock icon and your email address until you save or discard. Deleting a global block is blocked while any published content, draft page, or unsaved change still references it.

## Files and UUIDs

Each global block is a hidden Kirby page under `content/_global/<uuid>` (or your configured `root`), so a `files` field uploads there by default. Without copying files during conversion, the block keeps pointing to the host page's files, so deleting that page or its files breaks them everywhere.

File fields rely on Kirby's `file://` UUIDs so references resolve on any page. Global blocks stay outside Kirby's site index so they never appear in page lists, and the plugin registers them and their files in Kirby's `uuid` cache. Do not turn off UUIDs or the `uuid` cache.

## Limits

Only `toBlocks()` and `toLayouts()` resolve references. Raw field reads, including `$site->search()`, see only the reference.

Block types match by name against your configured `fieldsets` ([Options](3_reference#options)). Inline block types need a matching block blueprint with the same name, and host field `extends` overrides do not apply.

Global blocks have no public URLs. Previews require a Panel user with `access` or a preview token ([Preview template](3_reference#preview-template)).

For exact option names, permissions, and preview variables, continue to [Reference](3_reference).
