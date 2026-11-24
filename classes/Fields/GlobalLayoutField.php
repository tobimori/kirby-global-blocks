<?php

namespace tobimori\GlobalBlocks\Fields;

use Kirby\Form\Field\LayoutField;
use tobimori\GlobalBlocks\Fields\Concerns\HasGlobalBlocks;

class GlobalLayoutField extends LayoutField
{
	use HasGlobalBlocks;
}
