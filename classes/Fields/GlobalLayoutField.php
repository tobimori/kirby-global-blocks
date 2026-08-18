<?php

namespace tobimori\GlobalBlocks\Fields;

use Kirby\Cms\Layouts;
use Kirby\Form\Field\LayoutField;
use tobimori\GlobalBlocks\Fields\Concerns\HasGlobalBlocks;

class GlobalLayoutField extends LayoutField
{
	use HasGlobalBlocks;

	public function __construct(array $params)
	{
		$this->globalBlocksEnabled = ($params['globalBlocks'] ?? false) === true;
		parent::__construct($params);
	}

	public function fill(mixed $value): static
	{
		if ($this->globalBlocksEnabled) {
			$value = Layouts::parse($value);
			foreach ($value as &$layout) {
				$layout['columns'] ??= [];
				foreach ($layout['columns'] as &$column) {
					$column['blocks'] = $this->expandBlocks($column['blocks'] ?? []);
				}
				unset($column);
			}
			unset($layout);
		}

		return parent::fill($value);
	}

	public function type(): string
	{
		return 'layout';
	}
}
