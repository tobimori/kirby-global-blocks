<?php
/**
 * Fallback preview of a global block. Create `site/templates/global-block-preview.php`
 * to render the block with the styles of your site.
 *
 * @var \Kirby\Cms\Block|null $block
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($kirby->language()?->code() ?? 'en', 'attr') ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title><?= esc($page->title()->value()) ?></title>
	<style>
		body {
			margin: 0;
			padding: 4rem;
			font: 2.5rem/1.4 system-ui, sans-serif;
			color: #000;
			background: #fff;
		}

		img, video, iframe {
			max-width: 100%;
			height: auto;
		}
	</style>
</head>
<body>
	<?= $block ?>
</body>
</html>
