<template>
	<k-dialog
		:cancel-button="false"
		:class="[
			'k-block-selector',
			'k-global-block-selector',
			{ 'k-global-block-selector-split': globalBlocks.length },
			$attrs.class
		]"
		:size="size"
		:style="$attrs.style"
		:submit-button="false"
		:visible="true"
		@cancel="$emit('cancel')"
		@submit="$emit('submit', value)"
	>
		<div class="k-global-block-selector-columns">
			<div>
				<k-headline v-if="headline">
					{{ headline }}
				</k-headline>

				<details v-for="(group, groupName) in groups" :key="groupName" :open="group.open">
					<summary>{{ group.label }}</summary>
					<k-navigate class="k-block-types">
						<k-button
							v-for="fieldset in group.fieldsets"
							:key="fieldset.name"
							:disabled="disabledFieldsets.includes(fieldset.type)"
							:icon="fieldset.icon ?? 'box'"
							:text="fieldset.name"
							size="lg"
							@click="$emit('submit', fieldset.type)"
							@focus.native="$emit('input', fieldset.type)"
						/>
					</k-navigate>
				</details>

				<!-- eslint-disable-next-line vue/no-v-html -->
				<p v-if="clipboard" class="k-clipboard-hint" v-html="$t('field.blocks.fieldsets.paste', { shortcut })" />
			</div>

			<section v-if="globalBlocks.length">
				<h2 class="k-global-block-selector-label">{{ $t("global-blocks.globalPlural") }}</h2>
				<k-navigate class="k-global-block-cards">
					<div v-for="global in globalBlocks" :key="global.uuid" class="k-global-block-card">
						<k-global-block-preview :src="global.previewUrl" />
						<div class="k-global-block-card-content">
							<button
								class="k-global-block-card-button"
								type="button"
								@click="$emit('submit', `global:${global.uuid}`)"
							>
								{{ global.title }}
							</button>
							<!-- untitled blocks use the type name as title -->
							<p v-if="global.title !== global.typeName" class="k-global-block-card-info">
								{{ global.typeName }}
							</p>
							<p v-if="global.description" class="k-global-block-card-info">
								{{ global.description }}
							</p>
						</div>
					</div>
				</k-navigate>
			</section>
		</div>
	</k-dialog>
</template>

<script>
export default {
	extends: "k-block-selector",
	props: {
		clipboard: {
			type: Boolean,
			default: true
		},
		globalBlocks: {
			type: Array,
			default: () => []
		}
	}
}
</script>
