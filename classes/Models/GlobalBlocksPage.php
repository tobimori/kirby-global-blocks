<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Page;
use Kirby\Cms\PageBlueprint;
use Kirby\Cms\Pages;
use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
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

	// the container is not a page of the website
	public function render(array $data = [], $contentType = 'html', VersionId|string|null $versionId = null): string
	{
		throw new NotFoundException('Page not found');
	}
}
