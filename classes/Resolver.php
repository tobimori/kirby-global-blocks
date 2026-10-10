<?php

namespace tobimori\GlobalBlocks;

use Kirby\Cms\Blocks;
use Kirby\Cms\Layouts;
use Kirby\Content\Field;
use Kirby\Exception\InvalidArgumentException;

class Resolver
{
	public static function blocks(Field $field): Blocks
	{
		try {
			$value = static::resolve(Blocks::parse($field->value()));
			$blocks = Blocks::factory($value, [
				'field' => $field,
				'parent' => $field->parent(),
			]);

			return $blocks->filter('isHidden', false);
		} catch (\Throwable $exception) {
			throw new InvalidArgumentException(
				message: 'Invalid blocks data for "' . $field->key() . '" field',
				previous: $exception,
			);
		}
	}

	public static function layouts(Field $field): Layouts
	{
		return Layouts::factory(static::resolve(Layouts::parse($field->value())), [
			'field' => $field,
			'parent' => $field->parent(),
		]);
	}

	public static function resolve(array $value): array
	{
		if (isset($value['type'])) {
			return static::block($value) ?? [];
		}

		$result = [];
		foreach ($value as $item) {
			if (is_array($item) === false) {
				continue;
			}

			if (isset($item['columns'])) {
				foreach ($item['columns'] as &$column) {
					$column['blocks'] = static::resolve($column['blocks'] ?? []);
				}
				unset($column);
				$result[] = $item;
				continue;
			}

			if ($block = static::block($item)) {
				$result[] = $block;
			}
		}

		return $result;
	}

	public static function block(array $block): ?array
	{
		if (static::isReference($block) === false) {
			return $block;
		}

		// a reference cycle would render forever, it can only exist if content was changed outside the Panel
		if (GlobalBlocks::hasCycle($block['content']['uuid'])) {
			return null;
		}

		$global = GlobalBlocks::find($block['content']['uuid']);
		$source = $global?->block('latest');
		if ($source === null) {
			return null;
		}

		return [
			...$source->toArray(),
			'id' => $block['id'] ?? $source->id(),
			'isHidden' => $block['isHidden'] ?? false,
		];
	}

	public static function isReference(array $block): bool
	{
		return ($block['type'] ?? null) === 'global' && is_string($block['content']['uuid'] ?? null);
	}
}
