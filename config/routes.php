<?php

use Kirby\Content\VersionId;
use Kirby\Exception\PermissionException;
use tobimori\GlobalBlocks\GlobalBlocks;

return [
	[
		'pattern' => 'global-block-preview/(:any)',
		'action' => function (string $uuid): string {
			$kirby = kirby();
			$global = GlobalBlocks::findOrFail($uuid);
			$versionId = VersionId::from($kirby->request()->get('_version', 'latest'));
			$version = $global->version($versionId);

			if (
				$versionId->is('changes')
				&& $kirby->user() === null
				&& hash_equals($version->previewToken(), $kirby->request()->get('_token', '')) === false
			) {
				throw new PermissionException('The preview is not available');
			}

			$template = $kirby->template('global-block-preview');
			if ($template->exists() === false) {
				$template = $kirby->template('default');
			}

			return VersionId::render($versionId, function () use ($global, $kirby, $template, $versionId): string {
				$language = $kirby->request()->get('language', 'current');
				$blockVersionId = $versionId->value;
				if ($versionId->is('changes') && $global->version('changes')->exists($language) === false) {
					$blockVersionId = 'latest';
				}

				$data = $global->controller([
					'block' => $global->block($blockVersionId, $language),
				]);
				$kirby->data = $data;

				return $template->render($data);
			});
		},
	],
];
