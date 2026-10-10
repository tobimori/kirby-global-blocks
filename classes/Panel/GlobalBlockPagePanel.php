<?php

namespace tobimori\GlobalBlocks\Panel;

use Kirby\Panel\Page;

class GlobalBlockPagePanel extends Page
{
	public function dropdown(array $options = []): array
	{
		$delete = parent::dropdown($options)['delete'];
		if (($delete['disabled'] ?? false) === true) {
			return [];
		}

		// the plugin dialog checks usages and redirects to the global blocks view
		$delete['dialog'] = '/global-blocks/' . $this->model->globalUuid() . '/delete';
		return [$delete];
	}
}
