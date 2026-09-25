<template>
	<k-panel-inside
		:data-id="id"
		:data-locked="isLocked"
		:data-template="blueprint"
		class="k-page-view"
	>
		<k-header
			:editable="permissions.changeTitle && !isLocked"
			class="k-page-view-header"
			@edit="$dialog(titleDialog)"
		>
			<template v-if="title">{{ title }}</template>
			<span v-else class="k-header-title-placeholder">{{ placeholder }} …</span>

			<template #buttons>
				<k-view-buttons :buttons="buttons">
					<template #after>
						<k-form-controls
							:editor="editor"
							:has-diff="hasDiff"
							:is-locked="isLocked"
							:is-processing="isSaving"
							:modified="modified"
							:preview="permissions.preview ? api + '/preview/changes' : false"
							@discard="onDiscard"
							@submit="onSubmit"
						/>
					</template>
				</k-view-buttons>
			</template>
		</k-header>

		<k-model-tabs :diff="diff" :tab="tab.name" :tabs="tabs" />

		<k-sections
			:blueprint="blueprint"
			:content="content"
			:empty="$t('page.blueprint', { blueprint: $esc(blueprint) })"
			:lock="lock"
			:parent="api"
			:tab="tab"
			@input="onInput"
			@submit="onSubmit"
		/>
	</k-panel-inside>
</template>

<script>
export default {
	extends: "k-page-view",
	props: {
		placeholder: String,
		titleDialog: String
	}
}
</script>
