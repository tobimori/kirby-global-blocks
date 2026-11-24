---
title: Getting started
intro: Install Kirby Global Blocks, create your first reusable block, and update two pages at once
---

In this tutorial, you will install Kirby Global Blocks, enable it on a `blocks` field, and update two pages with one edit. The steps use the Kirby Starterkit's notes, though any `blocks` field works the same way.

## Requirements

Version 1.x supports Kirby 6 and PHP 8.3 or newer. Version 0.x supports Kirby 5 (5.5 or newer). Keep Kirby's UUIDs and `uuid` cache enabled.

## Install the plugin

Run Composer in your project folder:

```bash
composer require tobimori/kirby-global-blocks
```

<details>
<summary>Manual installation</summary>

Download the latest release from [GitHub](https://github.com/tobimori/kirby-global-blocks/releases) and extract it into `site/plugins/kirby-global-blocks`.

</details>

## Enable global blocks

Add `globalBlocks: true` to the `text` field in `site/blueprints/pages/note.yml`:

```yaml
# site/blueprints/pages/note.yml
columns:
  - width: 2/3
    fields:
      text:
        type: blocks
        globalBlocks: true
```

## Create and place a global block

1. Open the Panel, click **Global blocks** in the main menu, then click **Create global block** and pick **Text**.
2. Click the title placeholder, name the block "Newsletter", and save the title.
3. Write a short signup note in the block and click **Save**.
4. Open a note, click **Add** in the `text` field, pick **Newsletter** from the **Global blocks** column, and click **Save**.
5. Open a second note, add **Newsletter** to its `text` field, and click **Save**.

In the Panel, both notes now show the block with a colored header bar labeled **Newsletter**.

## Edit once and see both pages update

Edit the text inside **Newsletter** on the second note and click **Save**. Open both notes on the website. Both show the new text because `site/templates/note.php` renders the field with `toBlocks()`:

```php
<?= $page->text()->toBlocks() ?>
```

Next, [Working with global blocks](1_guides) covers everyday tasks like converting existing blocks, styling previews, and limiting permissions.
