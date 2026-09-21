<template>
	<k-field
		v-bind="$props"
		:class="['k-blocks-field', $attrs.class]"
		:input="false"
		:style="$attrs.style"
	>
		<template v-if="!isGlobalBlockEditor && !disabled && hasFieldsets" #options>
			<k-button-group layout="collapsed">
				<k-button
					:autofocus="autofocus"
					:disabled="isFull"
					:responsive="true"
					:text="$t('add')"
					class="input-focus"
					icon="add"
					size="xs"
					variant="filled"
					@click="$refs.blocks.choose(value.length)"
				/>
				<k-button
					:title="$t('options')"
					icon="dots"
					size="xs"
					variant="filled"
					@click="$refs.options.toggle()"
				/>
				<k-dropdown-content ref="options" :options="options" align-x="end" />
			</k-button-group>
		</template>

		<k-input-validator v-bind="{ min, max, required }" :value="JSON.stringify(value)">
			<k-blocks
				ref="blocks"
				v-bind="$props"
				@close="opened = $event"
				@input="$emit('input', $event)"
				@open="opened = $event"
			/>
		</k-input-validator>

		<footer v-if="!isGlobalBlockEditor && !disabled && !isEmpty && !isFull && hasFieldsets">
			<k-button
				:title="$t('add')"
				icon="add"
				size="xs"
				variant="filled"
				@click="$refs.blocks.choose(value.length)"
			/>
		</footer>
	</k-field>
</template>

<script>
export default {
	extends: "k-blocks-field",
	props: {
		globalBlocks: {
			type: [Array, Boolean],
			default: false
		}
	},
	provide() {
		return {
			globalBlocksContext: () => this.globalBlocks
		}
	},
	computed: {
		isGlobalBlockEditor() {
			return this.endpoints?.model?.replace(/^\//, "").startsWith("pages/_global+") === true
		}
	}
}
</script>
