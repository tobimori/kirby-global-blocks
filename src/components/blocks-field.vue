<template>
	<k-field
		v-bind="$props"
		:class="['k-blocks-field', $attrs.class]"
		:input="false"
		:style="$attrs.style"
	>
		<template v-if="!globalBlockType && !disabled && hasFieldsets" #options>
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
				<k-dropdown ref="options" :options="options" align-x="end" />
			</k-button-group>
		</template>

		<k-validator v-bind="{ min, max, required }" :count="value.length">
			<k-blocks
				ref="blocks"
				v-bind="$props"
				@close="opened = $event"
				@input="$emit('input', $event)"
				@open="opened = $event"
			/>
		</k-validator>

		<footer v-if="!globalBlockType && !disabled && !isEmpty && !isFull && hasFieldsets">
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
import GlobalBlocksField from "../mixins/global-blocks-field.js"

export default {
	extends: "k-blocks-field",
	mixins: [GlobalBlocksField],
	props: {
		globalBlockType: String
	}
}
</script>
