<template>
	<k-panel-inside class="k-global-blocks-view">
		<k-header>
			{{ $t("global-blocks.title") }}
			<template #buttons>
				<k-view-button
					v-if="canCreate"
					:text="$t('global-blocks.create')"
					icon="add"
					@click="$dialog('global-blocks/create')"
				/>
			</template>
		</k-header>

		<k-collection v-if="items.length" :items="items" />
		<k-empty v-else :disabled="!canCreate" icon="global-block" @click="$dialog('global-blocks/create')">
			{{ $t("global-blocks.empty") }}
		</k-empty>
	</k-panel-inside>
</template>

<script>
export default {
	props: {
		canCreate: Boolean,
		items: {
			type: Array,
			default: () => []
		}
	}
}
</script>
