<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Block;
use Kirby\Cms\Blocks;
use Kirby\Cms\Page;
use Kirby\Cms\PageBlueprint;
use Kirby\Content\Field;
use Kirby\Toolkit\Str;
use tobimori\GlobalBlocks\GlobalBlocks;

class GlobalBlockPage extends Page
{
	public function block(string|null $version = null, string|null $language = null): Block|null
	{
		$version ??= 'latest';
		$language ??= 'current';
		$value = $this->version($version)->content($language)->get('block')->value();

		return Blocks::factory(Blocks::parse($value), [
			'parent' => $this,
		])->first();
	}

	public function blockForPanel(): Block|null
	{
		$language = $this->kirby()->language()?->code() ?? 'default';
		$version = $this->version('changes')->exists($language) ? 'changes' : 'latest';
		return $this->block($version, $language);
	}

	public function blockType(): string
	{
		return $this->blockForPanel()?->type() ?? 'text';
	}

	public function blueprint(): PageBlueprint
	{
		return $this->blueprint ??= new PageBlueprint([
			'buttons' => ['languages', 'settings'],
			'image' => ['icon' => 'globe'],
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
				'list' => false,
				'move' => false,
				'preview' => false,
				'read' => GlobalBlocks::permission('access'),
				'sort' => false,
				'update' => GlobalBlocks::permission('update'),
			],
			'tabs' => [
				'content' => [
					'label' => 'Content',
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
								'meta' => [
									'type' => 'fields',
									'fields' => [
										'title' => [
											'label' => 'global-blocks.titleLabel',
											'type' => 'text',
										],
										'description' => [
											'label' => 'global-blocks.descriptionLabel',
											'type' => 'textarea',
											'buttons' => false,
										],
									],
								],
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

	public function panelData(): array
	{
		$block = $this->blockForPanel();
		$fieldset = $block ? GlobalBlocks::fieldset($block->type()) : null;

		return [
			'apiUrl' => $this->apiUrl(true),
			'description' => $this->description()->value(),
			'fieldEndpoint' => $this->apiUrl(true) . '/fields/block',
			'icon' => $fieldset?->icon() ?? 'box',
			'lock' => $this->lock()->toArray(),
			'panelUrl' => $this->panel()->url(true),
			'title' => $this->title()->value(),
			'type' => $block?->type(),
			'uuid' => $this->globalUuid(),
		];
	}

	public function title(): Field
	{
		if ($title = $this->content()->get('title')->value()) {
			return new Field($this, 'title', $title);
		}

		try {
			$title = GlobalBlocks::fieldset($this->blockType())->name();
		} catch (\Throwable) {
			$title = Str::ucfirst($this->blockType());
		}

		return new Field($this, 'title', $title);
	}
}
