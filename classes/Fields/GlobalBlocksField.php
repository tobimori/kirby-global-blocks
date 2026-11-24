<?php

namespace tobimori\GlobalBlocks\Fields;

use Kirby\Cms\Fieldset;
use Kirby\Cms\Fieldsets;
use Kirby\Cms\Page;
use Kirby\Form\Field\BlocksField;
use Kirby\Reflection\Attributes\Prop;
use tobimori\GlobalBlocks\Fields\Concerns\HasGlobalBlocks;
use tobimori\GlobalBlocks\GlobalBlocks;

class GlobalBlocksField extends BlocksField
{
	use HasGlobalBlocks;

	/**
	 * Block type of the field in the global block editor
	 */
	#[Prop]
	protected ?string $globalBlockType = null;

	public function fieldsets(): Fieldsets
	{
		if ($this->globalBlockType === null) {
			return parent::fieldsets();
		}

		if ($this->fieldsetsCollection !== null) {
			return $this->fieldsetsCollection;
		}

		// the plugin option can define block types inline, which a lookup by blueprint name does not find
		$model = $this->model();
		$global = GlobalBlocks::fieldsets($model instanceof Page ? $model : null)->filter(
			fn(Fieldset $fieldset) => $fieldset->type() === $this->globalBlockType,
		);

		// a block type that was removed from the option keeps its blueprint, so the Panel does not break
		return $this->fieldsetsCollection = $global->count() > 0
			? $global
			: Fieldsets::factory([$this->globalBlockType], ['parent' => $model]);
	}
}
