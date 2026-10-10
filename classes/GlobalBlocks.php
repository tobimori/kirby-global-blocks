<?php

namespace tobimori\GlobalBlocks;

use Kirby\Cms\App;
use Kirby\Cms\Block;
use Kirby\Cms\Fieldset;
use Kirby\Cms\Fieldsets;
use Kirby\Cms\File;
use Kirby\Cms\Languages;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Site;
use Kirby\Cms\User;
use Kirby\Data\Json;
use Kirby\Data\Yaml;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuids;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;
use tobimori\GlobalBlocks\Models\GlobalBlocksPage;

class GlobalBlocks
{
	protected static ?GlobalBlocksPage $container = null;
	protected static ?App $app = null;

	/** @var array<string, bool> */
	protected static array $cycles = [];

	protected static ?Fieldsets $fieldsets = null;
	protected static bool $buildingFieldsets = false;

	public static function container(): GlobalBlocksPage
	{
		// a new app instance can use a different content root
		if (static::$app !== App::instance()) {
			static::$app = App::instance();
			static::$container = null;
			static::$cycles = [];
			static::$fieldsets = null;
		}

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
		$page = static::find($uuid) ?? $page;

		if (Uuids::enabled()) {
			$page->uuid()?->populate();
		}

		return $page;
	}

	/**
	 * Creates a global block from a block of a host field.
	 * The content must already be stored values. With `$copyFiles`, the files
	 * of the host model that the block uses are copied to the new global block,
	 * otherwise the global block keeps the references to the host files.
	 */
	public static function createFromBlock(
		string $type,
		array $content,
		ModelWithContent $host,
		?string $title = null,
		?string $description = null,
		bool $copyFiles = false,
	): GlobalBlockPage {
		static::authorize('create');
		static::fieldset($type);

		$files = $copyFiles ? static::hostFiles($content, $host) : [];
		$global = static::create(type: $type, content: $content, title: $title, description: $description);
		if ($files === []) {
			return $global;
		}

		$json = Json::encode($content);
		foreach ($files as $reference => $file) {
			$copy = $file->copy($global);
			$json = str_replace($reference, $copy->uuid()?->toString() ?? $reference, $json);
		}

		// the creator does not need the update permission for this step
		$global->version('latest')->update([
			'block' => Json::encode([[
				...$global->block('latest', 'default')->toArray(),
				'content' => Json::decode($json),
			]]),
		], 'default');

		static::purge();
		return static::findOrFail($global->globalUuid());
	}

	public static function createWithDefaults(
		string $type,
		?string $title = null,
		?string $description = null,
	): GlobalBlockPage {
		static::authorize('create');
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
			static::fieldsets()->find($type) ?? throw new InvalidArgumentException(
				'The block type "' . $type . '" is not available for global blocks',
			)
		);
	}

	public static function fieldsets(?Page $parent = null): Fieldsets
	{
		static::container();

		if ($parent === null && static::$fieldsets !== null) {
			return static::$fieldsets;
		}

		// a block type with a nested blocks field asks for the global fieldsets while they are built
		if (static::$buildingFieldsets) {
			return new Fieldsets([]);
		}

		static::$buildingFieldsets = true;

		try {
			$fieldsets = static::buildFieldsets($parent);
		} finally {
			static::$buildingFieldsets = false;
		}

		if ($parent === null) {
			static::$fieldsets = $fieldsets;
		}

		return $fieldsets;
	}

	protected static function buildFieldsets(?Page $parent): Fieldsets
	{
		$kirby = App::instance();
		$definitions = $kirby->option('tobimori.global-blocks.fieldsets');

		if (is_array($definitions) === false) {
			$definitions = $kirby->option('blocks.fieldsets', [
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
				foreach (Dir::files($kirby->root('blueprints') . '/blocks') as $file) {
					$name = F::name($file);
					if (str_starts_with($name, '_') === false && in_array($name, $definitions, true) === false) {
						$definitions[] = $name;
					}
				}

				// core blueprints that are not in the default list (like table) are not used by default fields
				$core = $kirby->core()->blueprints();
				foreach ($kirby->extensions('blueprints') as $name => $blueprint) {
					if (str_starts_with($name, 'blocks/') === false || ($core[$name] ?? null) === $blueprint) {
						continue;
					}

					$name = substr($name, 7);
					if (in_array($name, $definitions, true) === false) {
						$definitions[] = $name;
					}
				}
			}
		}

		$fieldsets = Fieldsets::factory($definitions, ['parent' => $parent ?? $kirby->site()]);

		foreach ((array) $kirby->option('tobimori.global-blocks.fieldsetsIgnore', []) as $type) {
			$fieldsets->remove($type);
		}

		return $fieldsets;
	}

	/**
	 * Returns the files of the host model that the content references, keyed by reference
	 *
	 * @return array<string, File>
	 */
	public static function hostFiles(array $content, ModelWithContent $host): array
	{
		$files = [];
		foreach (static::fileReferencesIn($content) as $reference) {
			$file = App::instance()->file($reference, $host);
			// the UUID strings include the model type, `file://` references need UUIDs anyway
			if ($file instanceof File && $file->parent()->uuid()?->toString() === $host->uuid()?->toString()) {
				$files[$reference] = $file;
			}
		}

		return $files;
	}

	/**
	 * Returns all `file://` references in a value
	 */
	public static function fileReferencesIn(mixed $value): array
	{
		preg_match_all('!file://[a-zA-Z0-9_-]+!', is_string($value) ? $value : Json::encode($value), $matches);
		return array_values(array_unique($matches[0]));
	}

	public static function items(): Pages
	{
		$pages = new Pages([], static::container());
		$kirby = App::instance();
		$extensions = [$kirby->contentExtension()];

		if ($kirby->multilang()) {
			foreach ($kirby->languages() as $language) {
				$extensions[] = $language->code() . '.' . $kirby->contentExtension();
			}
		}

		foreach (Dir::dirs(static::root()) as $directory) {
			if (static::validUuid($directory) === false) {
				continue;
			}

			foreach ($extensions as $extension) {
				if (is_file(static::root() . '/' . $directory . '/global-block.' . $extension)) {
					$pages->append($directory, static::make($directory));
					break;
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

	/**
	 * Throws if the global block references itself, directly or through other global blocks.
	 * The `$block` replaces the stored block of `$uuid` when given.
	 * The `latest` version is used for the frontend, the `panel` version
	 * uses the changes version when it exists.
	 */
	public static function assertNoCycle(string $uuid, ?array $block = null, string $version = 'panel'): void
	{
		$visit = function (string $current, array $path) use (&$visit, $uuid, $block, $version): void {
			if (in_array($current, $path, true)) {
				throw new InvalidArgumentException('Global blocks cannot reference themselves recursively');
			}

			$path[] = $current;
			$global = static::find($current);
			$data = match (true) {
				$current === $uuid && $block !== null => $block,
				$version === 'latest' => $global?->block('latest')?->toArray(),
				default => $global?->blockForPanel()?->toArray(),
			};

			if ($data === null) {
				return;
			}

			foreach (static::referencesIn($data) as $reference) {
				$visit($reference, $path);
			}
		};

		$visit($uuid, []);
	}

	/**
	 * Checks the published content for a reference cycle, the result is cached for the request
	 */
	public static function hasCycle(string $uuid): bool
	{
		static::container();

		if (isset(static::$cycles[$uuid]) === false) {
			try {
				static::assertNoCycle($uuid, version: 'latest');
				static::$cycles[$uuid] = false;
			} catch (InvalidArgumentException) {
				static::$cycles[$uuid] = true;
			}
		}

		return static::$cycles[$uuid];
	}

	/**
	 * Feeds the UUIDs of all global blocks and their files into the UUID cache.
	 * Kirby looks up UUIDs in the site index, which does not contain global blocks.
	 */
	public static function populateUuids(): void
	{
		$root = static::root();
		if (Uuids::enabled() === false || is_dir($root) === false) {
			return;
		}

		// the directory times change when global blocks, files or content change
		$state = [];
		foreach (Dir::dirs($root) as $directory) {
			if (static::validUuid($directory)) {
				$state[$directory] = filemtime($root . '/' . $directory);
			}
		}

		$cache = App::instance()->cache('uuid');
		$key = 'global-blocks/state';
		$hash = md5($root . Json::encode($state));

		if ($cache->get($key) === $hash) {
			return;
		}

		foreach (static::items() as $global) {
			$global->uuid()?->populate();

			foreach ($global->files() as $file) {
				$file->uuid()?->populate();
			}
		}

		$cache->set($key, $hash);
	}

	public static function purge(): void
	{
		static::$container?->purge();
		static::$cycles = [];
	}

	public static function referencesIn(mixed $value): array
	{
		$references = [];
		$walk = function (mixed $item) use (&$walk, &$references): void {
			if (is_string($item)) {
				// cheap check before the value is decoded
				if (str_contains($item, 'global') === false) {
					return;
				}

				$trimmed = trim($item);
				try {
					if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
						$walk(Json::decode($trimmed));
					} elseif (str_contains($trimmed, "\n")) {
						// structure and object fields store YAML, nested blocks are JSON strings in it
						$walk(Yaml::decode($trimmed));
					}
				} catch (\Throwable) {
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

	/**
	 * Finds all content that references the global block,
	 * in the latest and changes versions of the site, pages, users, files and other global blocks
	 */
	public static function usages(string $uuid): array
	{
		$kirby = App::instance();
		$usages = [];
		$models = [
			$kirby->site(),
			...$kirby->site()->index(true)->values(),
			...static::items()->values(),
			...$kirby->users()->values(),
		];

		foreach ($models as $model) {
			array_push($models, ...$model->files()->values());
		}

		foreach ($models as $model) {
			foreach (Languages::ensure() as $language) {
				foreach (['latest', 'changes'] as $versionId) {
					$version = $model->version($versionId);
					if ($version->exists($language) === false) {
						continue;
					}

					foreach ($version->read($language) ?? [] as $field => $value) {
						if (
							is_string($value) === false
							|| str_contains($value, $uuid) === false
							|| in_array($uuid, static::referencesIn($value), true) === false
						) {
							continue;
						}

						$usages[$model->id() . ':' . $field . ':' . $language->code()] = [
							'field' => $field,
							'id' => $model->id(),
							'language' => $language->code(),
							'link' => $model instanceof GlobalBlockPage
								? $model->globalPanelUrl(true)
								: $model->panel()->url(true),
							'text' => match (true) {
								$model instanceof File => $model->filename(),
								$model instanceof User => $model->username(),
								$model instanceof Site, $model instanceof Page => $model->title()->value(),
								default => $model->id(),
							},
						];
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
