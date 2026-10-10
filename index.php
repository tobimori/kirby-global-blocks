<?php

spl_autoload_register(function (string $class): void {
	$prefix = 'tobimori\\GlobalBlocks\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$file = __DIR__ . '/classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	if (is_file($file)) {
		require_once $file;
	}
});

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use tobimori\GlobalBlocks\Fields\GlobalBlocksField;
use tobimori\GlobalBlocks\Fields\GlobalLayoutField;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;
use tobimori\GlobalBlocks\Models\GlobalBlocksPage;

if (
	version_compare(App::version() ?? '0.0.0', '5.5.0', '<')
	|| version_compare(App::version() ?? '0.0.0', '6.0.0', '>=')
) {
	throw new Exception('Kirby Global Blocks requires Kirby 5.5 or newer (Kirby 5)');
}

App::plugin('tobimori/global-blocks', [
	'options' => require __DIR__ . '/config/options.php',
	'fields' => [
		'blocks' => GlobalBlocksField::class,
		'layout' => GlobalLayoutField::class,
	],
	'fieldMethods' => require __DIR__ . '/config/field-methods.php',
	'pageModels' => [
		'global-block' => GlobalBlockPage::class,
		'global-blocks' => GlobalBlocksPage::class,
	],
	'pages' => [
		'_global' => GlobalBlocks::container(),
	],
	'templates' => [
		'global-block-preview' => __DIR__ . '/templates/global-block-preview.php',
	],
	'areas' => require __DIR__ . '/config/areas.php',
	'routes' => require __DIR__ . '/config/routes.php',
	'hooks' => require __DIR__ . '/config/hooks.php',
	'permissions' => [
		'access' => true,
		'create' => true,
		'update' => true,
		'delete' => true,
	],
	'translations' => A::keyBy(A::map(Dir::files(__DIR__ . '/translations'), function (string $file): array {
		$translations = [];
		foreach (Json::read(__DIR__ . '/translations/' . $file) as $key => $value) {
			$translations['global-blocks.' . $key] = $value;
		}

		return A::merge(['lang' => F::name($file)], $translations);
	}), 'lang'),
]);
