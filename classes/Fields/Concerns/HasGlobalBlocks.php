<?php

namespace tobimori\GlobalBlocks\Fields\Concerns;

use Kirby\Toolkit\Str;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

trait HasGlobalBlocks
{
	protected bool $globalBlocksEnabled = false;

	public function blocksToValues(array $blocks, string $to = 'toFormValues'): array
	{
		if ($this->globalBlocksEnabled === false) {
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
			if ($reference = $this->referenceFromExpanded($block)) {
				if (in_array($to, ['content', 'toStoredValues'], true)) {
					$result[] = $reference;
					continue;
				}
			}

			$result[] = parent::blocksToValues([$block], $to)[0] ?? $block;
		}

		return $result;
	}

	public function props(): array
	{
		return [
			...parent::props(),
			'globalBlocks' => $this->globalBlocksEnabled ? $this->globalOptions() : false,
		];
	}

	public function routes(): array
	{
		$routes = parent::routes();
		$field = $this;

		$routes[] = [
			'pattern' => 'global-blocks/(:any)',
			'method' => 'GET',
			'action' => fn(string $uuid) => $field->expandedReference(GlobalBlocks::findOrFail($uuid), Str::uuid()),
		];

		$routes[] = [
			'pattern' => 'global-blocks',
			'method' => 'POST',
			'action' => function () use ($field) {
				$request = $field->model()->kirby()->request();
				$block = $request->get('block');
				if (is_array($block) === false) {
					throw new \Kirby\Exception\InvalidArgumentException('Invalid block');
				}

				$field->fieldset($block['type'] ?? '');
				$global = GlobalBlocks::create(type: $block['type'], content: array_diff_key($block['content'] ?? [], [
					'_global' => null,
				]), title: $request->get('title'), description: $request->get('description'));

				return $field->expandedReference($global, $block['id'] ?? Str::uuid(), $block['isHidden'] ?? false);
			},
		];

		return $routes;
	}

	public function expandedReference(GlobalBlockPage $global, string $placementId, bool $isHidden = false): array
	{
		$source = $global->blockForPanel();
		if ($source === null) {
			return $this->missingReference($global->globalUuid(), $placementId, $isHidden);
		}

		$data = $source->toArray();
		$data['id'] = $placementId;
		$data['isHidden'] = $isHidden;
		$data['content']['_global'] = [
			...$global->panelData(),
			'blockId' => $source->id(),
		];

		return $data;
	}

	protected function expandBlocks(array $blocks): array
	{
		$result = [];
		foreach ($blocks as $block) {
			if (($block['type'] ?? null) === 'global' && is_string($block['content']['uuid'] ?? null)) {
				$global = GlobalBlocks::find($block['content']['uuid']);
				$result[] = $global
					? $this->expandedReference($global, $block['id'] ?? Str::uuid(), $block['isHidden'] ?? false)
					: $this->missingReference(
						$block['content']['uuid'],
						$block['id'] ?? Str::uuid(),
						$block['isHidden'] ?? false,
					);
				continue;
			}

			$result[] = $block;
		}

		return $result;
	}

	protected function globalOptions(): array
	{
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
			'content' => ['uuid' => $global['uuid']],
			'id' => $block['id'] ?? Str::uuid(),
			'isHidden' => $block['isHidden'] ?? false,
			'type' => 'global',
		];
	}
}
