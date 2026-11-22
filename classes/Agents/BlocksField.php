<?php

namespace tobimori\GlobalBlocks\Agents;

use tobimori\Agents\Content\InputCheck;
use tobimori\Agents\Fields\BlocksField as AgentsBlocksField;

class BlocksField extends AgentsBlocksField
{
	use HasGlobalBlocks;

	public function check(mixed $value, InputCheck $check, string $where): void
	{
		parent::check($value, $check, $where);
		$this->checkGlobalBlocks($value, $check, $where);
	}
}
