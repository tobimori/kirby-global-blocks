<?php

namespace tobimori\GlobalBlocks;

use Kirby\Cms\App;
use Kirby\Cms\Block;
use Kirby\Cms\Fieldset;
use Kirby\Cms\Fieldsets;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Data\Json;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;
use tobimori\GlobalBlocks\Models\GlobalBlocksPage;

class GlobalBlocks
{
	protected static ?GlobalBlocksPage $container = null;

	public static function container(): GlobalBlocksPage
	{
		return static::$container ??= new GlobalBlocksPage([
			'root' => static::root(),
			'slug' => '_global',
			'template' => 'global-blocks',
		]);
	}

	public static function create(
		string $type,
		array $content = [],
		?string $title = null,
		?string $description = null,
	): GlobalBlockPage {
		static::authorize('create');
		static::fieldset($type);

		$uuid = Str::uuid();
		$root = static::root() . '/' . $uuid;

		if (Dir::make($root) !== true) {
			throw new InvalidArgumentException('The global block directory could not be created');
		}

		$page = static::make($uuid);
		$block = Block::factory([
			'content' => $content,
			'id' => Str::uuid(),
			'type' => $type,
		]);

		try {
			$page->version('latest')->save([
				'block' => Json::encode([$block->toArray()]),
				'description' => $description,
				'title' => $title,
				'uuid' => $uuid,
			], 'default');
		} catch (\Throwable $exception) {
			Dir::remove($root);
			throw $exception;
		}

		static::purge();
		return static::find($uuid) ?? $page;
	}

	public static function createWithDefaults(
		string $type,
		?string $title = null,
		?string $description = null,
	): GlobalBlockPage {
		$fieldset = static::fieldset($type);
		$form = new \Kirby\Form\Form(fields: $fieldset->fields(), model: App::instance()->site(), language: 'current');
		$form->fill(input: $form->defaults());

		return static::create(type: $type, content: $form->toStoredValues(), title: $title, description: $description);
	}

	public static function find(string $uuid): ?GlobalBlockPage
	{
		if (static::validUuid($uuid) === false) {
			return null;
		}

		$page = static::container()->children()->find($uuid);
		return $page instanceof GlobalBlockPage ? $page : null;
	}

	public static function findOrFail(string $uuid): GlobalBlockPage
	{
		return static::find($uuid) ?? throw new NotFoundException('Global block not found');
	}

	public static function fieldset(string $type): Fieldset
	{
		return (
			static::fieldsets()->find($type) ?? throw new InvalidArgumentException('Unknown block type "' . $type . '"')
		);
	}

	public static function fieldsets(?Page $parent = null): Fieldsets
	{
		$definitions = App::instance()->option('tobimori.global-blocks.fieldsets');
		if (is_array($definitions) === false) {
			$definitions = App::instance()->option('blocks.fieldsets', [
				'code',
				'gallery',
				'heading',
				'image',
				'line',
				'list',
				'markdown',
				'quote',
				'text',
				'video',
			]);

			// a grouped config is complete, discovered blueprints are only added to a flat list
			if (A::some($definitions, fn($definition) => ($definition['type'] ?? null) === 'group') === false) {
				foreach (Dir::files(App::instance()->root('blueprints') . '/blocks') as $file) {
					$name = F::name($file);
					if (str_starts_with($name, '_') === false && in_array($name, $definitions, true) === false) {
						$definitions[] = $name;
					}
				}

				foreach (array_keys(App::instance()->extensions('blueprints')) as $name) {
					if (str_starts_with($name, 'blocks/')) {
						$name = substr($name, 7);
						if (in_array($name, $definitions, true) === false) {
							$definitions[] = $name;
						}
					}
				}
			}
		}

		$fieldsets = Fieldsets::factory($definitions, ['parent' => $parent ?? App::instance()->site()]);

		foreach ((array) App::instance()->option('tobimori.global-blocks.fieldsetsIgnore', []) as $type) {
			$fieldsets->remove($type);
		}

		return $fieldsets;
	}

	public static function items(): Pages
	{
		$pages = new Pages([], static::container());
		foreach (Dir::dirs(static::root()) as $directory) {
			if (
				static::validUuid($directory)
				&& is_file(static::root() . '/' . $directory . '/global-block.' . App::instance()->contentExtension())
			) {
				$pages->append($directory, static::make($directory));
				continue;
			}

			if (static::validUuid($directory) && App::instance()->multilang()) {
				foreach (App::instance()->languages() as $language) {
					if (
						is_file(
							static::root() . '/' . $directory . '/global-block.' . $language->code() . '.'
								. App::instance()->contentExtension(),
						)
					) {
						$pages->append($directory, static::make($directory));
						break;
					}
				}
			}
		}

		return $pages;
	}

	public static function make(string $uuid): GlobalBlockPage
	{
		return new GlobalBlockPage([
			'parent' => static::container(),
			'root' => static::root() . '/' . $uuid,
			'site' => App::instance()->site(),
			'slug' => $uuid,
			'template' => 'global-block',
		]);
	}

	public static function permission(string $action): bool
	{
		return App::instance()->user()?->role()->permissions()->for('tobimori.global-blocks', $action) ?? false;
	}

	public static function authorize(string $action): void
	{
		if (static::permission($action) === false) {
			throw new \Kirby\Exception\PermissionException('You are not allowed to ' . $action . ' global blocks');
		}
	}

	public static function assertNoCycle(string $uuid, array $block): void
	{
		$visit = function (string $current, array $path) use (&$visit, $uuid, $block): void {
			if (in_array($current, $path, true)) {
				throw new InvalidArgumentException('Global blocks cannot reference themselves recursively');
			}

			$path[] = $current;
			$data = $current === $uuid ? $block : static::find($current)?->blockForPanel()?->toArray();

			if ($data === null) {
				return;
			}

			foreach (static::referencesIn($data) as $reference) {
				$visit($reference, $path);
			}
		};

		$visit($uuid, []);
	}

	public static function purge(): void
	{
		static::$container?->purge();
	}

	public static function referencesIn(mixed $value): array
	{
		$references = [];
		$walk = function (mixed $item) use (&$walk, &$references): void {
			if (is_string($item)) {
				$trimmed = trim($item);
				if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
					try {
						$walk(Json::decode($trimmed));
					} catch (\Throwable) {
					}
				}
				return;
			}

			if (is_array($item) === false) {
				return;
			}

			if (($item['type'] ?? null) === 'global' && is_string($item['content']['uuid'] ?? null)) {
				$references[] = $item['content']['uuid'];
			}

			foreach ($item as $child) {
				$walk($child);
			}
		};

		$walk($value);
		return array_values(array_unique($references));
	}

	public static function root(): string
	{
		return App::instance()->option('tobimori.global-blocks.root') ?? App::instance()->root('content') . '/_global';
	}

	public static function usages(string $uuid): array
	{
		$usages = [];
		$models = [
			App::instance()->site(),
			...App::instance()->site()->index(true)->values(),
			...static::items()->values(),
		];

		foreach ($models as $model) {
			foreach (App::instance()->languages() as $language) {
				foreach (['latest', 'changes'] as $versionId) {
					$version = $model->version($versionId);
					if ($version->exists($language) === false) {
						continue;
					}

					foreach ($version->read($language) ?? [] as $field => $value) {
						if (in_array($uuid, static::referencesIn($value), true)) {
							$usages[$model->id() . ':' . $field . ':' . $language->code()] = [
								'field' => $field,
								'id' => $model->id(),
								'language' => $language->code(),
								'link' => $model instanceof GlobalBlockPage
									? $model->globalPanelUrl(true)
									: $model->panel()->url(true),
								'text' => $model->title()->value(),
							];
						}
					}
				}
			}
		}

		return array_values($usages);
	}

	public static function validUuid(string $uuid): bool
	{
		return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
	}
}
