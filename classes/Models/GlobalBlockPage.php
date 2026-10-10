<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Block;
use Kirby\Cms\Blocks;
use Kirby\Cms\Page;
use Kirby\Cms\PageBlueprint;
use Kirby\Content\Field;
use Kirby\Content\VersionId;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Panel\Page as PanelPage;
use Kirby\Toolkit\Str;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Panel\GlobalBlockPagePanel;

class GlobalBlockPage extends Page
{
	public function block(?string $version = null, ?string $language = null): ?Block
	{
		$version ??= 'latest';
		$language ??= 'current';
		$value = $this->version($version)->content($language)->get('block')->value();

		return Blocks::factory(Blocks::parse($value), [
			'parent' => $this,
		])->first();
	}

	public function blockForPanel(): ?Block
	{
		$language = $this->kirby()->language()?->code() ?? 'default';
		$version = $this->version('changes')->exists($language) ? 'changes' : 'latest';
		return $this->block($version, $language);
	}

	public function blockType(): string
	{
		return $this->blockForPanel()?->type() ?? 'text';
	}

	/**
	 * Last check before content is written, this also covers the changes version
	 */
	public function contentFileData(array $data, ?string $languageCode = null): array
	{
		$data = parent::contentFileData($data, $languageCode);
		$this->validateBlock($data['block'] ?? null);
		return $data;
	}

	/**
	 * Validates before Kirby moves this object into memory storage,
	 * an exception in `contentFileData()` would leave an unusable object
	 */
	public function update(?array $input = null, ?string $languageCode = null, bool $validate = false): static
	{
		if (is_array($input) && array_key_exists('block', $input)) {
			$this->validateBlock($input['block']);
		}

		return parent::update($input, $languageCode, $validate);
	}

	public function validateBlock(mixed $value): void
	{
		$blocks = Blocks::parse($value);

		if (count($blocks) !== 1 || isset($blocks[0]) === false || is_array($blocks[0]) === false) {
			throw new InvalidArgumentException(
				t('global-blocks.singleBlock') ?? 'A global block must contain exactly one block.',
			);
		}

		GlobalBlocks::assertNoCycle($this->globalUuid(), $blocks[0]);
	}

	public function blueprint(): PageBlueprint
	{
		return $this->blueprint ??= new PageBlueprint([
			'buttons' => ['preview', 'languages', 'settings'],
			'image' => ['icon' => 'global-block'],
			'model' => $this,
			'name' => 'global-block',
			'options' => [
				'access' => GlobalBlocks::permission('access'),
				'changeSlug' => false,
				'changeStatus' => false,
				'changeTemplate' => false,
				'changeTitle' => false,
				'create' => false,
				'delete' => GlobalBlocks::permission('delete'),
				'duplicate' => false,
				// global blocks are not in the site index, this only shows them in the changes dialog
				'list' => GlobalBlocks::permission('access'),
				'move' => false,
				'preview' => GlobalBlocks::permission('access'),
				'read' => GlobalBlocks::permission('access'),
				'sort' => false,
				'update' => GlobalBlocks::permission('update'),
			],
			'tabs' => [
				'content' => [
					'label' => t('Content') ?? 'Content',
					'columns' => [
						'main' => [
							'width' => '2/3',
							'fields' => [
								'block' => [
									'label' => false,
									'type' => 'blocks',
									'fieldsets' => [$this->blockType()],
									'globalBlocks' => false,
									'max' => 1,
									'min' => 1,
								],
							],
						],
						'sidebar' => [
							'width' => '1/3',
							'sections' => [
								'files' => [
									'type' => 'files',
								],
							],
						],
					],
				],
			],
			'title' => 'Global block',
		]);
	}

	public function globalUuid(): string
	{
		return $this->uid();
	}

	public function isGlobalBlock(): bool
	{
		return true;
	}

	public function globalPanelUrl(bool $relative = false): string
	{
		$path = '/global-blocks/' . $this->globalUuid();
		return $relative ? $path : $this->kirby()->url('panel') . $path;
	}

	public function panel(): PanelPage
	{
		return new GlobalBlockPagePanel($this);
	}

	public function panelData(): array
	{
		$language = $this->kirby()->language()?->code() ?? 'default';
		$hasChanges = $this->version('changes')->exists($language);
		$version = $hasChanges ? 'changes' : 'latest';
		$content = $this->version($version)->content($language);
		$block = $this->block($version, $language);
		// a removed block type must not break the Panel
		$fieldset = $block ? GlobalBlocks::fieldsets()->find($block->type()) : null;
		$title = $content->get('title')->value() ?? '';
		$typeName = $fieldset?->name() ?? Str::ucfirst($block?->type() ?? 'text');

		return [
			'apiUrl' => $this->apiUrl(true),
			'description' => $content->get('description')->value(),
			'fieldEndpoint' => $this->apiUrl(true) . '/fields/block',
			'hasChanges' => $hasChanges,
			'icon' => $fieldset?->icon() ?? 'box',
			'lock' => $this->lock()->toArray(),
			'panelUrl' => $this->globalPanelUrl(true),
			'permissions' => [
				'update' => GlobalBlocks::permission('update') && $this->permissions()->can('update'),
			],
			'previewUrl' => $this->previewUrl($version),
			'title' => $title !== '' ? $title : $typeName,
			'titleValue' => $title,
			'type' => $block?->type(),
			'typeName' => $typeName,
			'uuid' => $this->globalUuid(),
		];
	}

	public function previewUrl(VersionId|string $versionId = 'latest'): ?string
	{
		$version = $this->version($versionId);

		return (
			rtrim($this->kirby()->url('index'), '/')
			. '/global-block-preview/'
			. $this->globalUuid()
			. '?'
			. http_build_query([
				'_token' => $version->previewToken(),
				'_version' => (string) $version->id(),
				'language' => $this->kirby()->language()?->code(),
			])
		);
	}

	/**
	 * Global blocks are not pages of the website, the preview route renders them
	 */
	public function render(array $data = [], $contentType = 'html', VersionId|string|null $versionId = null): string
	{
		throw new NotFoundException('Global block not found');
	}

	public function title(): Field
	{
		if ($title = $this->content()->get('title')->value()) {
			return new Field($this, 'title', $title);
		}

		$title = GlobalBlocks::fieldsets()->find($this->blockType())?->name() ?? Str::ucfirst($this->blockType());

		return new Field($this, 'title', $title);
	}
}
