<?php

namespace tobimori\GlobalBlocks\Models;

use Kirby\Cms\Block;
use Kirby\Content\Content;
use Kirby\Content\Field;
use Kirby\Toolkit\BlockCollectionAccess;
use tobimori\GlobalBlocks\GlobalBlocks;

/**
 * Block model of global block placements. Content reads like `toBlocks()` and `toLayouts()`
 * pass their field and get the published global block. Forms create blocks without a field
 * and keep the reference, so that they store it again.
 */
class GlobalBlockReference extends Block
{
	protected ?Block $global = null;

	public function __construct(array $params)
	{
		parent::__construct($params);

		if ($this->field instanceof Field) {
			$this->global = $this->resolve($params);
		}
	}

	public function __call(string $method, array $args = []): mixed
	{
		if ($this->global !== null) {
			return $this->global->$method(...$args);
		}

		return parent::__call($method, $args);
	}

	public function content(): Content
	{
		return $this->global?->content() ?? parent::content();
	}

	#[BlockCollectionAccess]
	public function controller(): array
	{
		return $this->global?->controller() ?? parent::controller();
	}

	/**
	 * A missing global block is not rendered, `toBlocks()` and layout columns leave out hidden blocks
	 */
	public function isHidden(): bool
	{
		return parent::isHidden() || $this->field instanceof Field && $this->global === null;
	}

	public function toArray(): array
	{
		return $this->global?->toArray() ?? parent::toArray();
	}

	#[BlockCollectionAccess]
	public function toHtml(): string
	{
		return $this->global?->toHtml() ?? '';
	}

	public function type(): string
	{
		return $this->global?->type() ?? parent::type();
	}

	/**
	 * The published global block with the id and visibility of the placement,
	 * created with the block model of its type
	 */
	protected function resolve(array $params): ?Block
	{
		$uuid = parent::content()->get('uuid')->value();

		// a reference cycle would render forever, it can only exist if content was changed outside the Panel
		if (is_string($uuid) === false || GlobalBlocks::hasCycle($uuid)) {
			return null;
		}

		$source = GlobalBlocks::find($uuid)?->block('latest');
		if ($source === null) {
			return null;
		}

		return Block::factory([
			...$params,
			...$source->toArray(),
			'id' => $this->id(),
			'isHidden' => parent::isHidden(),
		]);
	}
}
