<?php

use Kirby\Api\Controller\Changes;
use Kirby\Cms\App;
use Kirby\Panel\Field;
use tobimori\GlobalBlocks\GlobalBlocks;

return [
	'global-blocks' => function () {
		if (GlobalBlocks::permission('access') === false) {
			return null;
		}

		return [
			'icon' => 'global-block',
			'label' => t('global-blocks.title') ?? 'Global blocks',
			'menu' => true,
			'views' => [
				'global-block' => [
					'pattern' => 'global-blocks/(:any)',
					'action' => function (string $uuid): array {
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
						$view['props']['permissions']['changeTitle'] = GlobalBlocks::permission('update');
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
											'info' => $data['title'] !== $data['typeName'] ? $data['typeName'] : null,
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
						$request = App::instance()->request();
						Changes::save(GlobalBlocks::findOrFail($uuid), [
							'description' => $request->get('description', ''),
							'title' => trim($request->get('title', '')),
						]);

						return ['event' => 'global-blocks.updated'];
					},
				],
				'global-blocks/create' => [
					'load' => function () {
						GlobalBlocks::authorize('create');
						$fieldsets = GlobalBlocks::fieldsets();

						return [
							'component' => 'k-block-selector',
							'props' => [
								'class' => 'k-global-blocks-selector',
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
		];
	},
];
