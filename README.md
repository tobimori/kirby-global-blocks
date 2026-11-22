# Kirby Global Blocks

Reusable global blocks for Kirby blocks and layout fields

---

## Get started

```bash
composer require tobimori/kirby-global-blocks
```

Version 0.x supports Kirby 5 (5.5 or newer). Version 1.x will support Kirby 6 (coming soon).

Enable global blocks on any `blocks` or `layout` field in your blueprints:

```yaml
fields:
  text:
    type: blocks
    globalBlocks: true
```

Read the [documentation](https://www.andkindness.com/global-blocks/docs/getting-started) for Panel editing, templates, files, permissions and options.

## License

[MIT](LICENSE.md)

Copyright 2026 © Tobias Möritz - Love & Kindness GmbH
