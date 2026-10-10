<?php

use Kirby\Content\VersionId;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use tobimori\GlobalBlocks\GlobalBlocks;

return [
	[
		'pattern' => 'global-block-preview/(:any)',
		'action' => function (string $uuid): string {
			$kirby = kirby();
			$request = $kirby->request();
			$global = GlobalBlocks::findOrFail($uuid);
			$versionId = VersionId::from($request->get('_version', 'latest'));
			$version = $global->version($versionId);
			$token = $request->get('_token', '');

			// global blocks are not public, the Panel adds a preview token to each preview URL
			if (
				GlobalBlocks::permission('access') === false
				&& (is_string($token) === false || hash_equals($version->previewToken(), $token) === false)
			) {
				throw new PermissionException('The preview is not available');
			}

			$language = 'default';
			if ($kirby->multilang()) {
				$code = $request->get('language');
				$language = is_string($code) && $code !== ''
					? $kirby->language($code) ?? throw new NotFoundException('The language does not exist')
					: $kirby->defaultLanguage();
				$kirby->setCurrentLanguage($language->code());
				$language = $language->code();
			}

			// the plugin ships a fallback, site/templates/global-block-preview.php overrides it
			$template = $kirby->template('global-block-preview');

			return VersionId::render($versionId, function () use (
				$global,
				$kirby,
				$language,
				$template,
				$versionId,
			): string {
				$blockVersionId = $versionId->value();
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
