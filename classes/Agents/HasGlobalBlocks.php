<?php

namespace tobimori\GlobalBlocks\Agents;

use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use tobimori\Agents\Content\InputCheck;
use tobimori\Agents\Content\Node;
use tobimori\Agents\Content\Presenter;
use tobimori\Agents\Fields\Fields;
use tobimori\Agents\Schema\Compiler;
use tobimori\Agents\Tools\ToolError;
use tobimori\GlobalBlocks\Fields\GlobalBlocksField;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

/**
 * Global blocks in the blocks and layout field classes of Kirby Agents.
 * Agents get the values of the Panel, where a global block is a block with `_global` in its content
 *
 * @require-extends \tobimori\Agents\Fields\BlocksField
 */
trait HasGlobalBlocks
{
	protected const GLOBAL_BLOCKS_HINT = 'A global block shows the same content on several pages. Change its content with content_update on its `_global/…` id, which changes it on all these pages. Only if the user wants different content on this page alone, remove it here and insert a normal block';

	public function describe(Compiler $schema): string
	{
		$description = parent::describe($schema);

		if ($this->allowsGlobalBlocks() === false) {
			return $description;
		}

		return (
			$description
			. '. Global blocks are allowed: insert one with {"type": "global", "content": {"id": "_global/…"}}, global_blocks_find lists the ids. '
			. self::GLOBAL_BLOCKS_HINT
		);
	}

	public function summary(mixed $value, Presenter $presenter): string
	{
		$summary = parent::summary($value, $presenter);

		return str_contains((string) json_encode($value), '"_global":')
			? $summary . '. ' . self::GLOBAL_BLOCKS_HINT
			: $summary;
	}

	public function newItem(string $kind, array $op, array $content): array
	{
		if ($kind !== 'block' || ($op['type'] ?? null) !== 'global') {
			return parent::newItem($kind, $op, $content);
		}

		return $this->placement($content['id'] ?? $content['uuid'] ?? null);
	}

	public function nodeSummary(Node $node, mixed $value, Presenter $presenter): string
	{
		$summary = parent::nodeSummary($node, $value, $presenter);
		$global = $node->kind === 'block' ? static::globalData($value) : null;

		return match (true) {
			$global === null => $summary,
			$global['missing'] => 'deleted global block ' . $global['id'] . ', remove it',
			default => 'global block "' . $global['title'] . '" (' . $global['id'] . '): ' . $summary,
		};
	}

	protected function block(array $path, mixed $block, Presenter $presenter): array
	{
		$data = parent::block($path, $block, $presenter);
		$global = static::globalData($block);

		if ($global === null) {
			return $data;
		}

		return [
			...$data,
			'global' => array_filter([
				'id' => $global['id'],
				'title' => $global['title'],
				'missing' => $global['missing'] ?: null,
			]),
		];
	}

	/**
	 * A new whole value can keep and add global blocks like `insert` does
	 */
	protected function blocksInput(array $blocks, string $where): array
	{
		$blocks = array_values($blocks);
		$types = $this->blockTypes();

		foreach ($blocks as $index => $block) {
			$block = A::wrap($block);
			$type = is_string($block['type'] ?? null) ? $block['type'] : 'unknown';
			$number = $index + 1;

			// blocks that content_get shows with `global` are global blocks too
			$id = $type === 'global'
				? $block['content']['id'] ?? $block['content']['uuid'] ?? null
				: $block['global']['id'] ?? null;

			if ($id !== null) {
				$placement = $this->placement(
					$id,
					is_string($block['id'] ?? null) ? $block['id'] : null,
					($block['isHidden'] ?? false) === true,
				);

				// check() then reports changed content instead of losing it
				if ($type !== 'global' && is_array($block['content'] ?? null)) {
					$placement['content'] = [
						...$placement['content'],
						...Fields::input(
							$this->fieldset($placement['type']),
							$block['content'],
							[],
							"{$where} > block {$number} ({$placement['type']})",
						),
					];
				}

				$blocks[$index] = $placement;
				continue;
			}

			if (in_array($type, $types, true) === false) {
				throw new ToolError('`type` must be one of: ' . implode(', ', $types));
			}

			$block['content'] = Fields::input(
				$this->fieldset($type),
				A::wrap($block['content'] ?? null),
				[],
				"{$where} > block {$number} ({$type})",
			);
			$blocks[$index] = $block;
		}

		return $blocks;
	}

	/**
	 * Reports changes to the content of global blocks, which the plugin would not store
	 */
	protected function checkGlobalBlocks(mixed $blocks, InputCheck $check, string $where): void
	{
		foreach (array_values(is_array($blocks) ? $blocks : []) as $index => $block) {
			$global = static::globalData($block);

			// isNew() must see every global block, also in the check of the stored values
			if ($global === null || $global['missing'] || $check->isNew($block) === false) {
				continue;
			}

			$page = GlobalBlocks::find($global['uuid']);

			if ($page === null || static::changed($page, $block) === false) {
				continue;
			}

			$check->error(
				"{$where} > block "
				. ($index + 1)
				. ": this is the global block \"{$global['title']}\", which other pages show too, so its content cannot change here. Change it with content_update on `{$global['id']}`, which changes it on all these pages. Only if the user wants different content on this page alone, remove it and insert a normal block",
			);
		}
	}

	protected function allowsGlobalBlocks(): bool
	{
		return ($this->props['globalBlocks'] ?? false) === true;
	}

	/**
	 * The global block as the Panel gets it, with the id of the block on this page
	 */
	protected function placement(mixed $id, ?string $placementId = null, bool $isHidden = false): array
	{
		if ($this->allowsGlobalBlocks() === false) {
			throw new ToolError("`{$this->name()}` does not allow global blocks");
		}

		$uuid = is_string($id) ? (string) preg_replace('!^_global/!', '', $id) : '';
		$page = GlobalBlocks::find($uuid) ?? throw new ToolError(
			'No global block `' . (is_string($id) ? $id : '') . '`. global_blocks_find lists the ids',
		);

		if (in_array($page->blockType(), $this->blockTypes(), true) === false) {
			throw new ToolError(
				"The global block `_global/{$uuid}` is a {$page->blockType()} block. `{$this->name()}` only allows: "
					. implode(', ', $this->blockTypes()),
			);
		}

		return static::expand($page, $placementId ?? Str::uuid(), $isHidden);
	}

	protected static function expand(GlobalBlockPage $page, string $placementId, bool $isHidden = false): array
	{
		return static::globalField($page)->expandedFormValue($page, $placementId, $isHidden);
	}

	/**
	 * Compares stored values, because agents send files and pages in a shorter form than the Panel
	 */
	protected static function changed(GlobalBlockPage $page, mixed $block): bool
	{
		$field = static::globalField($page);
		$stored = static fn(mixed $block): mixed => (
			$field->blocksToValues([[
				'type' => $page->blockType(),
				'content' => static::content($block),
			]], 'toStoredValues')[0]['content'] ?? null
		);

		// not ===, the key order can differ
		return $stored($block) != $stored($field->expandedFormValue($page, Str::uuid()));
	}

	protected static function globalField(GlobalBlockPage $page): GlobalBlocksField
	{
		return new GlobalBlocksField(
			model: $page,
			name: 'block',
			type: 'blocks',
			globalBlocks: true,
			globalBlockType: $page->blockType(),
		);
	}

	/**
	 * @return array{id: string, uuid: string, title: string, missing: bool}|null
	 */
	protected static function globalData(mixed $block): ?array
	{
		$global = is_array($block) ? $block['content']['_global'] ?? null : null;

		if (is_array($global) === false || is_string($global['uuid'] ?? null) === false) {
			return null;
		}

		return [
			'id' => '_global/' . $global['uuid'],
			'missing' => ($global['missing'] ?? false) === true,
			'title' => is_string($global['title'] ?? null) ? $global['title'] : '',
			'uuid' => $global['uuid'],
		];
	}

	protected static function content(mixed $block): array
	{
		$content = is_array($block) && is_array($block['content'] ?? null) ? $block['content'] : [];
		unset($content['_global']);

		return $content;
	}
}
