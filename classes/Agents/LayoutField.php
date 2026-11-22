<?php

namespace tobimori\GlobalBlocks\Agents;

use tobimori\Agents\Content\InputCheck;
use tobimori\Agents\Fields\LayoutField as AgentsLayoutField;

class LayoutField extends AgentsLayoutField
{
	use HasGlobalBlocks;

	public function check(mixed $value, InputCheck $check, string $where): void
	{
		parent::check($value, $check, $where);

		foreach (array_values(is_array($value) ? $value : []) as $r => $row) {
			$columns = is_array($row) && is_array($row['columns'] ?? null) ? $row['columns'] : [];

			foreach (array_values($columns) as $c => $column) {
				$this->checkGlobalBlocks(
					is_array($column) ? $column['blocks'] ?? [] : [],
					$check,
					"{$where} > row " . ($r + 1) . ' > column ' . ($c + 1),
				);
			}
		}
	}
}
