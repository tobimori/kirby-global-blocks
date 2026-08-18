<?php

use Kirby\Content\LockedContentException;
use Kirby\Exception\PermissionException;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

return [
	'route:before' => function ($path, $method): void {
		if (
			$method !== 'POST' ||
			preg_match('!/pages/_global\+([^/]+)/changes/save$!', $path, $matches) !== 1
		) {
			return;
		}

		$block = kirby()->request()->get('block');
		if (is_array($block) && isset($block[0])) {
			GlobalBlocks::assertNoCycle($matches[1], $block[0]);
		}
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
				t('global-blocks.deleteReferenced')
					?? 'This global block is still used and cannot be deleted.'
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
		if (
			$method === 'POST' &&
			preg_match('!/pages/_global\+[^/]+/changes/publish$!', $path)
		) {
			kirby()->cache('pages')->flush();
		}

		return $result;
	},
];
