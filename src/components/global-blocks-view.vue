<template>
	<k-panel-inside class="k-global-blocks-view">
		<k-header>
			{{ $t("global-blocks.title") }}
			<template #buttons>
				<k-view-button
					v-if="canCreate"
					:text="$t('global-blocks.create')"
					icon="add"
					@click="create"
				/>
			</template>
		</k-header>

		<div v-if="items.length" class="k-items k-cards-items" data-layout="cards" data-size="medium">
			<k-item
				v-for="item in items"
				:key="item.id"
				:info="item.info"
				:link="item.link"
				:text="item.text"
				layout="cards"
			>
				<template #image>
					<k-global-block-preview :src="item.preview" />
				</template>
			</k-item>
		</div>
		<k-empty v-else :disabled="!canCreate" icon="global-block" @click="create">
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
	},
	methods: {
		create() {
			// the block selector submits the type as a string, the dialog route expects an object
			this.$panel.dialog.open("global-blocks/create", {
				on: {
					submit: async (type) => {
						const response = await this.$panel.dialog.post({ type })
						if (response) {
							this.$panel.dialog.success(response.$dialog ?? {})
						}
					}
				}
			})
		}
	}
}
</script>
