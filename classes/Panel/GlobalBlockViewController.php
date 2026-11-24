<?php

namespace tobimori\GlobalBlocks\Panel;

use Kirby\Panel\Controller\View\PageViewController;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

/**
 * @property GlobalBlockPage $model
 */
class GlobalBlockViewController extends PageViewController
{
	public static function factory(string $id): static
	{
		GlobalBlocks::authorize('access');
		return new static(GlobalBlocks::findOrFail($id));
	}

	public function breadcrumb(): array
	{
		return [
			[
				'label' => $this->title(),
				'link' => $this->model->globalPanelUrl(true),
			],
		];
	}

	public function component(): string
	{
		return 'k-global-block-view';
	}

	public function next(): ?array
	{
		return null;
	}

	public function prev(): ?array
	{
		return null;
	}

	public function props(): array
	{
		$props = parent::props();

		return [
			...$props,
			'link' => $this->model->globalPanelUrl(true),
			'permissions' => [...$props['permissions'], 'changeTitle' => $props['permissions']['update']],
			'titleDialog' => 'global-blocks/' . $this->model->globalUuid() . '/changeTitle',
			'untitled' => $this->model->content()->get('title')->isEmpty(),
		];
	}
}
