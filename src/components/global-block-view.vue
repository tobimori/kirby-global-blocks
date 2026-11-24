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
			<span v-if="untitled" class="k-header-title-placeholder">{{ title }} …</span>
			<template v-else>{{ title }}</template>

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

		<k-model-form
			:api="api"
			:columns="tab.columns"
			:content="content"
			:diff="diff"
			:lock="lock"
			@input="onInput"
			@submit="onSubmit"
		/>
	</k-panel-inside>
</template>

<script>
export default {
	extends: "k-page-view",
	props: {
		titleDialog: String,
		untitled: Boolean
	}
}
</script>
