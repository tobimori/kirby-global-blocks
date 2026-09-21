<?php

namespace tobimori\GlobalBlocks\Panel;

use Kirby\Panel\Page;

class GlobalBlockPagePanel extends Page
{
	public function dropdown(array $options = []): array
	{
		$delete = parent::dropdown($options)['delete'];
		return ($delete['disabled'] ?? false) === false ? [$delete] : [];
	}
}
