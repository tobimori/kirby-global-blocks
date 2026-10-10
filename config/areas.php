<?php

use Kirby\Cms\App;
use Kirby\Content\LockedContentException;
use Kirby\Exception\PermissionException;
use Kirby\Panel\Field;
use Kirby\Toolkit\Escape;
use Kirby\Toolkit\I18n;
use tobimori\GlobalBlocks\GlobalBlocks;

return [
	// an area must always return an array, the menu entry and views check the permission
	'global-blocks' => fn() => [
		'icon' => 'global-block',
		'label' => t('global-blocks.title') ?? 'Global blocks',
		'menu' => GlobalBlocks::permission('access'),
		'views' => [
			'global-block' => [
				'pattern' => 'global-blocks/(:any)',
				'action' => function (string $uuid): array {
					GlobalBlocks::authorize('access');
					$global = GlobalBlocks::findOrFail($uuid);
					$data = $global->panelData();
					$view = $global->panel()->view();
					$view['component'] = 'k-global-block-view';
					$view['breadcrumb'] = [
						[
							'label' => $data['title'],
							'link' => $global->globalPanelUrl(true),
						],
					];
					$view['props']['link'] = $global->globalPanelUrl(true);
					$view['props']['permissions']['changeTitle'] = $data['permissions']['update'];
					$view['props']['placeholder'] = $data['typeName'];
					$view['props']['title'] = $data['titleValue'];
					$view['props']['titleDialog'] = 'global-blocks/' . $uuid . '/changeTitle';
					$view['props']['model']['link'] = $global->globalPanelUrl(true);
					$view['props']['model']['parent'] = '/global-blocks';
					$view['props']['next'] = null;
					$view['props']['prev'] = null;

					return $view;
				},
			],
			'global-blocks' => [
				'pattern' => 'global-blocks',
				'action' => function () {
					GlobalBlocks::authorize('access');

					return [
						'component' => 'k-global-blocks-view',
						'props' => [
							'canCreate' => GlobalBlocks::permission('create'),
							'items' => function (): array {
								$items = [];
								foreach (GlobalBlocks::items() as $global) {
									$data = $global->panelData();
									$items[] = [
										'id' => $data['uuid'],
										// untitled blocks use the type name as title
										'info' => implode(' · ', array_filter([
											$data['title'] !== $data['typeName'] ? $data['typeName'] : null,
											$data['description'],
										])),
										'link' => $data['panelUrl'],
										'preview' => $data['previewUrl'],
										'text' => $data['title'],
									];
								}

								return $items;
							},
						],
					];
				},
			],
		],
		'dialogs' => [
			'global-block.changeTitle' => [
				'pattern' => 'global-blocks/(:any)/changeTitle',
				'load' => function (string $uuid): array {
					GlobalBlocks::authorize('update');
					$data = GlobalBlocks::findOrFail($uuid)->panelData();

					return [
						'component' => 'k-form-dialog',
						'props' => [
							'fields' => [
								'title' => Field::title([
									'placeholder' => $data['typeName'],
									'preselect' => true,
								]),
								'description' => [
									'buttons' => false,
									'label' => t('description'),
									'type' => 'textarea',
								],
							],
							'submitButton' => t('change'),
							'value' => [
								'description' => $data['description'],
								'title' => $data['titleValue'],
							],
						],
					];
				},
				'submit' => function (string $uuid): array {
					GlobalBlocks::authorize('update');
					$kirby = App::instance();
					$global = GlobalBlocks::findOrFail($uuid);

					if ($global->permissions()->can('update') === false) {
						throw new PermissionException('You are not allowed to update this global block');
					}

					if ($global->lock()->isLocked()) {
						throw new LockedContentException($global->lock());
					}

					$values = [
						'description' => $kirby->request()->get('description', ''),
						'title' => trim($kirby->request()->get('title', '')),
					];

					// like the core title dialog, change the published content directly
					// and keep the changes version in sync, so that publishing it does not undo the change
					if ($global->version('changes')->exists('current')) {
						$global->version('changes')->update($values, 'current');
					}

					$global->version('latest')->update($values, 'current');
					$kirby->cache('pages')->flush();

					return ['event' => 'global-blocks.updated'];
				},
			],
			'global-block.delete' => [
				'pattern' => 'global-blocks/(:any)/delete',
				'load' => function (string $uuid): array {
					GlobalBlocks::authorize('delete');
					$global = GlobalBlocks::findOrFail($uuid);
					$usages = [];

					foreach (GlobalBlocks::usages($global->globalUuid()) as $usage) {
						$usages[$usage['link']] ??=
							'<li><a href="'
							. Escape::attr(App::instance()->url('panel') . $usage['link'])
							. '">'
							. Escape::html($usage['text'] ?: $usage['id'])
							. '</a></li>';
					}

					if ($usages !== []) {
						return [
							'component' => 'k-text-dialog',
							'props' => [
								'submitButton' => false,
								'text' =>
									'<p>'
										. Escape::html(
											t('global-blocks.usedBy')
											?? 'This global block is still used and cannot be deleted:',
										)
										. '</p><ul>'
										. implode('', $usages)
										. '</ul>',
							],
						];
					}

					return [
						'component' => 'k-remove-dialog',
						'props' => [
							'text' => I18n::template('page.delete.confirm', replace: [
								'title' => Escape::html($global->title()->value()),
							]),
						],
					];
				},
				'submit' => function (string $uuid): array {
					// the page.delete:before hook checks the permission, lock and usages
					GlobalBlocks::findOrFail($uuid)->delete();

					return [
						'event' => 'page.delete',
						'redirect' => '/global-blocks',
					];
				},
			],
			'global-blocks/create' => [
				'load' => function () {
					GlobalBlocks::authorize('create');
					$fieldsets = GlobalBlocks::fieldsets();

					return [
						'component' => 'k-global-block-selector',
						'props' => [
							// pasted blocks cannot become global blocks here
							'clipboard' => false,
							'fieldsetGroups' => $fieldsets->groups() ?: null,
							'fieldsets' => $fieldsets->toArray(fn($fieldset) => [
								'icon' => $fieldset->icon(),
								'name' => $fieldset->name(),
								'type' => $fieldset->type(),
							]),
						],
					];
				},
				'submit' => function () {
					$global = GlobalBlocks::createWithDefaults(type: App::instance()->request()->get('type'));

					return [
						'event' => 'global-blocks.created',
						'redirect' => $global->globalPanelUrl(true),
					];
				},
			],
		],
	],
];
