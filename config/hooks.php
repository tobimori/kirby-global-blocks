<?php

use Kirby\Content\LockedContentException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Panel;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

return [
	'system.loadPlugins:after' => function (): void {
		GlobalBlocks::populateUuids();
	},
	'panel.route:before' => function ($route, $path) {
		if ($path === 'pages/_global') {
			Panel::go(kirby()->url('panel') . '/global-blocks');
		}

		if (
			is_string($path) && preg_match(
				'!^pages/_global\+([^/]+)$!',
				$path,
				$matches,
			) === 1 && ($global = GlobalBlocks::find($matches[1]))
		) {
			Panel::go($global->globalPanelUrl());
		}

		return $route;
	},
	'page.delete:before' => function ($page): void {
		if ($page instanceof GlobalBlockPage === false) {
			return;
		}

		GlobalBlocks::authorize('delete');
		if ($page->lock()->isLocked()) {
			throw new LockedContentException($page->lock());
		}

		if (GlobalBlocks::usages($page->globalUuid()) !== []) {
			throw new PermissionException(
				t('global-blocks.deleteReferenced') ?? 'This global block is still used and cannot be deleted.',
			);
		}
	},
	'page.delete:after' => function ($page): void {
		if ($page instanceof GlobalBlockPage) {
			GlobalBlocks::purge();
			kirby()->cache('pages')->flush();
		}
	},
	'route:after' => function ($path, $method, $result) {
		if ($method === 'POST' && preg_match('!/pages/_global\+[^/]+/changes/publish$!', $path)) {
			kirby()->cache('pages')->flush();
		}

		return $result;
	},
];
