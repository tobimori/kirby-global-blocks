<?php

namespace tobimori\GlobalBlocks\Fields;

use Kirby\Cms\Blocks;
use Kirby\Form\Field\BlocksField;
use tobimori\GlobalBlocks\Fields\Concerns\HasGlobalBlocks;

class GlobalBlocksField extends BlocksField
{
	use HasGlobalBlocks;

	public function __construct(array $params = [])
	{
		$this->globalBlocksEnabled = ($params['globalBlocks'] ?? false) === true;
		parent::__construct($params);
	}

	public function fill(mixed $value): static
	{
		if ($this->globalBlocksEnabled) {
			$value = $this->expandBlocks(Blocks::parse($value));
		}

		return parent::fill($value);
	}

	public function type(): string
	{
		return 'blocks';
	}
}
