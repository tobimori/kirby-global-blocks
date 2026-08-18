<?php

use Kirby\Cms\App;
use tobimori\GlobalBlocks\GlobalBlocks;

return [
	'global-blocks' => function () {
		if (GlobalBlocks::permission('access') === false) {
			return null;
		}

		return [
			'breadcrumb' => [
				[
					'label' => t('global-blocks.title') ?? 'Global blocks',
					'link' => '/global-blocks',
				],
			],
			'icon' => 'globe',
			'label' => t('global-blocks.title') ?? 'Global blocks',
			'menu' => true,
			'views' => [
				'global-block' => [
					'pattern' => 'global-blocks/(:any)',
					'action' => function (string $uuid): array {
						$global = GlobalBlocks::findOrFail($uuid);
						$view = $global->panel()->view();
						$view['breadcrumb'] = [
							[
								'label' => $global->title()->value(),
								'link' => $global->globalPanelUrl(true),
							],
						];
						$view['props']['link'] = $global->globalPanelUrl(true);
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
											'info' => $data['description'] ?: $data['type'],
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
							description: $request->get('description')
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
