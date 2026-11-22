---
title: Working with global blocks
intro: The everyday tasks after your first global block
---

In [Getting started](0_getting-started), you placed one global block on two pages. This page covers the tasks that come up after that.

## Turn an existing block into a global block

1. Open a page with an enabled field, open the block's menu, and choose **Turn into global block**.
2. Enter a title and optional description.
3. If the block uses files from this page, choose whether to **Copy files to the global block**. Without a copy, the global block keeps using the files of the page ([Files and UUIDs](2_concepts#files-and-uuids)).
4. Submit the dialog and click **Save**.

Clicking **Discard** before saving deletes the new global block unless another page uses it. If **Turn into global block** is missing, check [Options](3_reference#options) and `create` in [Permissions](3_reference#permissions).

## Layout and nested fields

Add `globalBlocks: true` to a `layout` field and render it with `$page->layout()->toLayouts()`:

```yaml
# site/blueprints/pages/about.yml
fields:
  layout:
    type: layout
    layouts:
      - "1/1"
      - "1/2, 1/2"
    globalBlocks: true
```

The same option works on `blocks` fields inside `structure`, `object`, or custom block blueprints. Inline block types need a matching `site/blueprints/blocks/<name>.yml` file before they can become global blocks ([Limits](2_concepts#limits)).

## Detach, hide, copy, and paste a placement

Open a global block's menu on a page and choose **Detach from global block** to turn it into a normal block, or **Hide** to hide only that placement, then click **Save**. Deleting a global block on a page removes only that placement.

Duplicating a global block or pasting it into a field with `globalBlocks: true` adds another placement. Pasting into a field without `globalBlocks: true` creates a normal copy.

## Delete a global block that is still used

1. Open the block in **Global blocks**, open its settings menu, and choose **Delete**.
2. If it is still used, follow each link in the dialog to remove, detach, or replace each placement (including draft pages and unsaved changes) and save.
3. Return to the block and choose **Delete** again.

## Limit editor permissions

Every Panel role can create, edit, and delete global blocks by default. To make global blocks read-only for a role, restrict `tobimori.global-blocks` in its user blueprint:

```yaml
# site/blueprints/users/editor.yml
title: Editor
permissions:
  tobimori.global-blocks:
    access: true
    create: false
    update: false
    delete: false
```

Set `access: false` to hide **Global blocks** while still letting editors place existing blocks from the selector. Core `pages` permissions also apply ([Permissions](3_reference#permissions)).

## Show previews with your site styles

Create `site/templates/global-block-preview.php` to style previews in **Global blocks**, the block selector, and the **Preview** button:

```php
<?php snippet('header') ?>
<?= $block ?>
<?php snippet('footer') ?>
```

If your site header crowds preview cards, load only your stylesheet around `<?= $block ?>`. See [Preview template](3_reference#preview-template) for variables and access rules.

## Store uploads on a shared page

A `files` field inside a global block uploads to that global block by default ([Files and UUIDs](2_concepts#files-and-uuids)). To upload to a shared `images` page instead, set `query` and `uploads.parent`:

```yaml
# site/blueprints/blocks/banner.yml
name: Banner
fields:
  image:
    type: files
    max: 1
    query: site.find('images').images
    uploads:
      parent: site.find('images')
      template: image
```

Uploads from a `banner` global block then go to `images` from both **Global blocks** and inline edits.

To see how references, locks, and file storage behave across pages, continue to [How it works](2_concepts).
