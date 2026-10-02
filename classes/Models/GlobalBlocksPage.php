<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Page;
use Kirby\Cms\PageBlueprint;
use Kirby\Cms\Pages;
use tobimori\GlobalBlocks\GlobalBlocks;

class GlobalBlocksPage extends Page
{
	// built lazily, constructing a blueprint while plugins load would populate the translation caches too early
	public function blueprint(): PageBlueprint
	{
		return $this->blueprint ??= new PageBlueprint([
			'model' => $this,
			'name' => 'pages/global-blocks',
			'options' => false,
			'title' => 'Global blocks',
		]);
	}

	public function children(): Pages
	{
		return GlobalBlocks::items();
	}

	public function isGlobalBlocksContainer(): bool
	{
		return true;
	}
}
