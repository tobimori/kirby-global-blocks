<?php

namespace tobimori\GlobalBlocks\Agents;

use Kirby\Toolkit\Str;
use tobimori\Agents\Content\Presenter;
use tobimori\Agents\OAuth\Access;
use tobimori\Agents\OAuth\Scope;
use tobimori\Agents\Tools\Arguments;
use tobimori\Agents\Tools\Tool;
use tobimori\GlobalBlocks\GlobalBlocks;
use tobimori\GlobalBlocks\Models\GlobalBlockPage;

class GlobalBlocksFind implements Tool
{
	public function name(): string
	{
		return 'global_blocks_find';
	}

	public function definition(): array
	{
		return [
			'title' => 'Find global blocks',
			'description' => implode("\n", [
				'Lists the global blocks. A global block is one block that several pages show: a change to it shows on all of them. Content that repeats on pages, like a newsletter signup or contact details, is often a global block. pages_find and site_overview do not find global blocks, so search here.',
				'Each global block has a page id like `_global/…`. Read and change its content with content_get and content_update on this id, like a page with one block. The change shows on all pages after it is published. Only if the user wants different content on one page alone, remove the global block from that page and insert a normal block there.',
				'To show a global block on a page, insert {"type": "global", "content": {"id": "_global/…"}} into a blocks or layout field that allows global blocks (schema_get says so).',
			]),
			'inputSchema' => [
				'type' => 'object',
				'properties' => [
					'query' => [
						'type' => 'string',
						'description' => 'Search text for the title, description, block type and content',
					],
					'usages' => [
						'type' => 'boolean',
						'default' => false,
						'description' => 'True also lists the pages that show each global block, also in unsaved changes',
					],
				],
				'additionalProperties' => false,
			],
			'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false],
		];
	}

	public function scope(): string
	{
		return Scope::CONTENT_READ;
	}

	public function call(Arguments $arguments, Access $access): array
	{
		$query = Str::lower(trim($arguments->string('query') ?? ''));
		$usages = $arguments->bool('usages', false);
		$items = [];

		foreach (GlobalBlocks::items() as $page) {
			if ($page instanceof GlobalBlockPage === false) {
				continue;
			}

			$data = $page->panelData();
			$text = implode(' ', static::texts($page->blockForPanel()?->content()->toArray() ?? []));
			$item = [
				'id' => '_global/' . $page->globalUuid(),
				'title' => $data['title'],
				'type' => $data['type'],
				'description' => $data['description'] ?: null,
				'preview' => Presenter::short($text),
			];

			$search = Str::lower(implode(' ', [...array_filter($item, is_string(...)), $text]));

			if ($query !== '' && str_contains($search, $query) === false) {
				continue;
			}

			if ($usages) {
				$item['usedOn'] = array_values(array_unique(array_map(
					fn(array $usage): string => $usage['id'] ?? 'site',
					GlobalBlocks::usages($page->globalUuid()),
				)));
			}

			$items[] = array_filter($item, fn(mixed $value): bool => $value !== null);
		}

		return ['total' => count($items), 'globalBlocks' => $items];
	}

	/**
	 * @return list<string>
	 */
	protected static function texts(mixed $value): array
	{
		if (is_string($value)) {
			return [$value];
		}

		return is_array($value) ? array_merge([], ...array_map(static::texts(...), array_values($value))) : [];
	}
}
