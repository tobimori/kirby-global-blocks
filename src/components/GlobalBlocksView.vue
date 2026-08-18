<template>
	<k-panel-inside class="k-global-blocks-view">
		<k-header>
			{{ $t("global-blocks.title") }}
			<template #buttons>
				<k-button v-if="canCreate" icon="add" variant="filled" @click="$dialog('global-blocks/create')">
					{{ $t("global-blocks.create") }}
				</k-button>
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
