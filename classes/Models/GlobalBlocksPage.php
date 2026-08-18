<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use tobimori\GlobalBlocks\GlobalBlocks;

class GlobalBlocksPage extends Page
{
	public function children(): Pages
	{
		return GlobalBlocks::items();
	}

	public function isGlobalBlocksContainer(): bool
	{
		return true;
	}
}
