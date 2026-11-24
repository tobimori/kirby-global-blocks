<?php

namespace tobimori\GlobalBlocks\Fields\Concerns;

use Kirby\Reflection\Attributes\Prop;
use Kirby\Toolkit\Str;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

trait HasGlobalBlocks
{
	/**
	 * Allows editors to place global blocks and to turn blocks into global blocks
	 */
	#[Prop]
	protected bool $globalBlocks = false;

	/**
	 * The Panel fills one form with the latest and the changes version. A global block
	 * that is published in between must not make the two versions differ.
	 */
	protected array $globalPanelData = [];

	public function blocksToValues(array $blocks, string $to = 'toFormValues'): array
	{
		if ($this->globalBlocks === false) {
			foreach ($blocks as &$block) {
				if (is_array($block['content'] ?? null)) {
					unset($block['content']['_global']);
				}
			}
			unset($block);

			return parent::blocksToValues($blocks, $to);
		}

		$result = [];
		foreach ($blocks as $block) {
			$reference = $this->referenceFromExpanded($block);
			if ($reference !== null && $to === 'toStoredValues') {
				$result[] = $reference;
				continue;
			}

			if ($to === 'toFormValues') {
				$block = $this->expandBlock($block);
			}

			$result[] = parent::blocksToValues([$block], $to)[0] ?? $block;
		}

		return $result;
	}

	public function props(): array
	{
		// the Panel loads the global blocks when the block selector opens,
		// a list in the props would repeat for every nested blocks field
		return [
			...parent::props(),
			'globalBlocksCreatable' => $this->globalBlocks ? $this->creatableTypes() : [],
		];
	}

	/**
	 * Block types of this field that the user can turn into global blocks
	 */
	protected function creatableTypes(): array
	{
		if (GlobalBlocks::permission('create') === false) {
			return [];
		}

		$global = GlobalBlocks::fieldsets();
		$types = [];
		foreach ($this->fieldsets() as $fieldset) {
			if ($global->find($fieldset->type()) !== null) {
				$types[] = $fieldset->type();
			}
		}

		return $types;
	}

	public function api(): array
	{
		$routes = parent::api();
		$field = $this;

		$routes[] = [
			'pattern' => 'global-blocks',
			'method' => 'GET',
			'action' => fn() => $field->globalOptions(),
		];

		$routes[] = [
			'pattern' => 'global-blocks/(:any)',
			'method' => 'GET',
			'action' => fn(string $uuid) => $field->expandedFormValue(GlobalBlocks::findOrFail($uuid), Str::uuid()),
		];

		$routes[] = [
			'pattern' => 'global-blocks/files',
			'method' => 'POST',
			'action' => function () use ($field) {
				$block = $field->storedBlockFromRequest();

				// lets the Panel ask whether the files of the host should be copied
				return [
					'files' => array_values(array_map(
						fn($file) => $file->filename(),
						GlobalBlocks::hostFiles($block['content'], $field->model()),
					)),
				];
			},
		];

		$routes[] = [
			'pattern' => 'global-blocks',
			'method' => 'POST',
			'action' => function () use ($field) {
				$request = $field->model()->kirby()->request();
				$block = $field->storedBlockFromRequest();

				$global = GlobalBlocks::createFromBlock(
					type: $block['type'],
					content: $block['content'],
					host: $field->model(),
					title: $request->get('title'),
					description: $request->get('description'),
					copyFiles: in_array($request->get('copyFiles'), [true, 'true'], true),
				);

				return $field->expandedFormValue($global, $block['id'], $block['isHidden']);
			},
		];

		return $routes;
	}

	/**
	 * Reads the block from the request and converts the Panel form values
	 * to the stored values of this field, for example file objects to UUIDs
	 */
	public function storedBlockFromRequest(): array
	{
		$block = $this->model()->kirby()->request()->get('block');
		if (is_array($block) === false) {
			throw new \Kirby\Exception\InvalidArgumentException('Invalid block');
		}

		$type = is_string($block['type'] ?? null) ? $block['type'] : '';
		$this->fieldset($type);
		$id = is_string($block['id'] ?? null) ? $block['id'] : Str::uuid();

		$content = $this->blocksToValues([[
			'content' => array_diff_key(is_array($block['content'] ?? null) ? $block['content'] : [], [
				'_global' => null,
			]),
			'id' => $id,
			'type' => $type,
		]], 'toStoredValues')[0]['content'];

		return [
			'content' => $content,
			'id' => $id,
			'isHidden' => ($block['isHidden'] ?? false) === true,
			'type' => $type,
		];
	}

	/**
	 * The expanded reference with Panel form values, for example file objects instead of UUIDs
	 */
	public function expandedFormValue(GlobalBlockPage $global, string $placementId, bool $isHidden = false): array
	{
		$block = $this->expandedReference($global, $placementId, $isHidden);
		return $this->blocksToValues([$block], 'toFormValues')[0] ?? $block;
	}

	public function expandedReference(
		GlobalBlockPage $global,
		string $placementId,
		bool $isHidden = false,
		?string $revision = null,
	): array {
		$source = $global->blockForPanel();
		if ($source === null) {
			return $this->missingReference($global->globalUuid(), $placementId, $isHidden);
		}

		$data = $source->toArray();
		// the revision only marks the host content as changed, the placement keeps its id
		$data['id'] = $placementId;
		$data['isHidden'] = $isHidden;
		$data['content']['_global'] = [
			...($this->globalPanelData[$global->globalUuid()] ??= $global->panelData()),
			'blockId' => $source->id(),
			'revision' => $revision,
		];

		return $data;
	}

	protected function expandBlock(array $block): array
	{
		$uuid = $block['content']['uuid'] ?? null;
		if (($block['type'] ?? null) !== 'global' || is_string($uuid) === false) {
			return $block;
		}

		$id = $block['id'] ?? Str::uuid();
		$isHidden = $block['isHidden'] ?? false;
		$global = GlobalBlocks::find($uuid);
		if ($global === null) {
			return $this->missingReference($uuid, $id, $isHidden);
		}

		$revision = $block['content']['revision'] ?? null;
		return $this->expandedReference($global, $id, $isHidden, is_string($revision) ? $revision : null);
	}

	/**
	 * The global blocks whose block type is available in this field
	 */
	public function globalOptions(): array
	{
		if ($this->globalBlocks === false) {
			return [];
		}

		$options = [];
		foreach (GlobalBlocks::items() as $global) {
			if (
				$global instanceof GlobalBlockPage === false
				|| $this->fieldsets()->find($global->blockType()) === null
			) {
				continue;
			}

			$options[] = $global->panelData();
		}

		return $options;
	}

	protected function missingReference(string $uuid, string $placementId, bool $isHidden): array
	{
		return [
			'content' => [
				'_global' => [
					'missing' => true,
					'title' => t('global-blocks.missing') ?? 'The global block does not exist',
					'uuid' => $uuid,
				],
			],
			'id' => $placementId,
			'isHidden' => $isHidden,
			'type' => 'global',
		];
	}

	protected function referenceFromExpanded(array $block): ?array
	{
		$global = $block['content']['_global'] ?? null;
		if (is_array($global) === false || is_string($global['uuid'] ?? null) === false) {
			return null;
		}

		return [
			'content' => array_filter([
				'revision' => $global['revision'] ?? null,
				'uuid' => $global['uuid'],
			]),
			'id' => $block['id'] ?? Str::uuid(),
			'isHidden' => $block['isHidden'] ?? false,
			'type' => 'global',
		];
	}
}
