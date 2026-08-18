<?php

use Kirby\Content\Field;
use tobimori\GlobalBlocks\Resolver;

return [
	'toBlocks' => fn (Field $field) => Resolver::blocks($field),
	'toLayouts' => fn (Field $field) => Resolver::layouts($field),
];
