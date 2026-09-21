<?php

use Kirby\Api\Controller\Changes;
use Kirby\Cms\App;
use Kirby\Cms\PageRules;
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
						$view['props']['title'] = $data['title'];
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
											'image' => [
												'back' => 'pattern',
												'icon' => $data['icon'],
											],
											'info' => $data['typeName'],
											'link' => $data['panelUrl'],
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
										'preselect' => true,
										'required' => true,
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
									'title' => $data['title'],
								],
							],
						];
					},
					'submit' => function (string $uuid): array {
						GlobalBlocks::authorize('update');
						$request = App::instance()->request();
						$title = trim($request->get('title', ''));
						PageRules::validateTitleLength($title);
						Changes::save(GlobalBlocks::findOrFail($uuid), [
							'description' => $request->get('description', ''),
							'title' => $title,
						]);

						return ['event' => 'global-blocks.updated'];
					},
				],
				'global-blocks/create' => [
					'load' => function () {
						GlobalBlocks::authorize('create');
						$options = [];
						foreach (GlobalBlocks::fieldsets() as $fieldset) {
							$options[] = [
								'text' => $fieldset->name(),
								'value' => $fieldset->type(),
							];
						}

						return [
							'component' => 'k-form-dialog',
							'props' => [
								'fields' => [
									'type' => [
										'empty' => false,
										'label' => t('global-blocks.typeLabel'),
										'options' => $options,
										'required' => true,
										'type' => 'select',
									],
									'title' => [
										'label' => t('global-blocks.titleLabel'),
										'type' => 'text',
									],
									'description' => [
										'buttons' => false,
										'label' => t('global-blocks.descriptionLabel'),
										'type' => 'textarea',
									],
								],
								'submitButton' => t('global-blocks.create'),
							],
						];
					},
					'submit' => function () {
						$request = App::instance()->request();
						$global = GlobalBlocks::createWithDefaults(
							type: $request->get('type'),
							title: $request->get('title'),
							description: $request->get('description'),
						);

						return [
							'event' => 'global-blocks.created',
							'go' => $global->globalPanelUrl(true),
						];
					},
				],
			],
		];
	},
];
