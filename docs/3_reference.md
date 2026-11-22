---
title: Reference
intro: Blueprint option, PHP configuration options, permissions, and preview template variables
---

[How it works](2_concepts) explained how references, locks, and UUIDs behave. Use this reference to configure fields, options, roles, and previews.

## Blueprint option

Add `globalBlocks: true` (boolean, default `false`) to any `blocks` or `layout` field to allow placing and converting global blocks. Each field keeps its own `fieldsets` and lists only allowed block types.

## Options

Configure options in `site/config/config.php` under `tobimori.global-blocks`:

| Option            | Type            | Default | Description                                         |
| ----------------- | --------------- | ------- | --------------------------------------------------- |
| `root`            | `string\|null`  | `null`  | Custom storage path. Defaults to `content/_global`. |
| `fieldsets`       | `array\|null`   | `null`  | Allowed block types (flat list or groups).          |
| `fieldsetsIgnore` | `array<string>` | `[]`    | Block type names excluded from global blocks.       |

Without `fieldsets`, global blocks use Kirby's `blocks.fieldsets` (or core defaults), blueprints in `site/blueprints/blocks/` (skipping names starting with `_`), and plugin blueprints. If `blocks.fieldsets` uses groups, only types in those groups are included. `fieldsetsIgnore` removes types afterward, matched by name.

## Permissions

Registered under `tobimori.global-blocks` for [user blueprints](https://getkirby.com/docs/guide/users/permissions). All default to `true`.

| Permission | Default | Controls                                                                                                                |
| ---------- | ------- | ----------------------------------------------------------------------------------------------------------------------- |
| `access`   | `true`  | Access **Global blocks**, block views, and previews without a token. Without it, users can still place existing blocks. |
| `create`   | `true`  | Create global blocks and use **Turn into global block**.                                                                |
| `update`   | `true`  | Edit content, titles, and descriptions. Without it, global blocks are read-only.                                        |
| `delete`   | `true`  | Delete unused global blocks.                                                                                            |

Core `pages` permissions also apply (for example, `pages.update: false` blocks edits).

## Preview template

Create `site/templates/global-block-preview.php` to override the fallback preview. Preview cards render the template 1280px wide and scale it down. Opening a preview requires a Panel user with `access` or a preview token.

| Variable | Type                       | Description                                    |
| -------- | -------------------------- | ---------------------------------------------- |
| `$block` | `Kirby\Cms\Block\|null`    | Resolved block for the current language.       |
| `$page`  | `GlobalBlockPage` (`Page`) | Global block page (title, description, files). |
| `$site`  | `Kirby\Cms\Site`           | Kirby `$site` object.                          |
| `$kirby` | `Kirby\Cms\App`            | Kirby `$kirby` instance.                       |
