<?php

namespace tobimori\GlobalBlocks\Fields;

use Kirby\Cms\Blocks;
use Kirby\Cms\Fieldset;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\Page;
use Kirby\Form\Field\BlocksField;
use tobimori\GlobalBlocks\Fields\Concerns\HasGlobalBlocks;
use tobimori\GlobalBlocks\GlobalBlocks;

class GlobalBlocksField extends BlocksField
{
	use HasGlobalBlocks;

	// only set for the field of the global block editor
	protected ?string $globalBlockType = null;

	public function __construct(array $params = [])
	{
		$this->globalBlocksEnabled = ($params['globalBlocks'] ?? false) === true;
		$this->globalBlockType = $params['globalBlockType'] ?? null;
		parent::__construct($params);
	}

	public function fill(mixed $value): static
	{
		if ($this->globalBlocksEnabled) {
			$value = $this->expandBlocks(Blocks::parse($value));
		}

		return parent::fill($value);
	}

	protected function setFieldsets(string|array|null $fieldsets, ModelWithContent $model): void
	{
		if ($this->globalBlockType === null) {
			parent::setFieldsets($fieldsets, $model);
			return;
		}

		// the plugin option can define block types inline, which a lookup by blueprint name does not find
		$global = GlobalBlocks::fieldsets($model instanceof Page ? $model : null)->filter(
			fn(Fieldset $fieldset) => $fieldset->type() === $this->globalBlockType,
		);

		// a block type that was removed from the option keeps its blueprint, so the Panel does not break
		if ($global->count() === 0) {
			parent::setFieldsets([$this->globalBlockType], $model);
			return;
		}

		$this->fieldsets = $global;
	}

	public function type(): string
	{
		return 'blocks';
	}
}
