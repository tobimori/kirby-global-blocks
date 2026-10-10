<template>
	<div
		ref="container"
		:class="[
			'k-block-container',
			'k-block-container-fieldset-' + type,
			containerType ? 'k-block-container-type-' + containerType : '',
			{ 'k-global-block-container': globalBlock },
			$attrs.class
		]"
		:data-batched="isBatched"
		:data-disabled="isDisabled"
		:data-global="Boolean(globalBlock)"
		:data-global-locked="globalBlock?.lock?.isLocked === true"
		:data-hidden="isHidden"
		:data-id="id"
		:data-last-selected="isLastSelected"
		:data-selected="isSelected"
		:data-translate="fieldset.translate"
		:style="$attrs.style"
		:tabindex="isDisabled ? null : 0"
		@keydown.ctrl.j.prevent.stop="$emit('merge')"
		@keydown.ctrl.alt.down.prevent.stop="$emit('selectDown')"
		@keydown.ctrl.alt.up.prevent.stop="$emit('selectUp')"
		@keydown.ctrl.shift.down.prevent.stop="$emit('sortDown')"
		@keydown.ctrl.shift.up.prevent.stop="$emit('sortUp')"
		@keydown.ctrl.backspace.stop="backspace"
		@focus.stop="onFocus"
		@focusin.stop="onFocusIn"
	>
		<div v-if="globalBlock" class="k-global-block-marker">
			<k-icon type="global-block" />
			<span class="k-global-block-title" @dblclick.stop="renameGlobal">
				{{ globalBlock.title || $t("global-blocks.global") }}
			</span>
			<span v-if="globalBlock.lock?.isLocked" :title="lockedBy" class="k-global-block-lock">
				<k-icon type="lock" />
				<span v-if="globalBlock.lock.user?.email" class="k-global-block-lock-user">
					{{ globalBlock.lock.user.email }}
				</span>
			</span>
		</div>

		<p v-if="globalBlock?.missing" class="k-global-block-missing">
			{{ $t("global-blocks.missingInfo") }}
		</p>
		<div v-else :class="className" :data-disabled="isContentDisabled" class="k-block">
			<component
				:is="customComponent"
				ref="editor"
				v-bind="$props"
				:disabled="isContentDisabled"
				:tabs="tabs"
				v-on="listeners"
			/>
		</div>

		<k-block-options
			v-if="!isDisabled"
			ref="options"
			v-bind="{
				globalBlock,
				globalBlocksEnabled: canMakeGlobal,
				isBatched,
				isEditable,
				isFull,
				isGlobalBlockEditor,
				isHidden,
				isMergable,
				isRemovable: !isGlobalBlockEditor,
				isSplitable: globalBlock ? false : isSplitable()
			}"
			v-on="listenersForOptions"
		/>
	</div>
</template>

<script>
export default {
	extends: "k-block",
	computed: {
		globalBlock() {
			return this.content?._global ?? null
		},
		canMakeGlobal() {
			return this.blocksOwner()?.creatableGlobalTypes?.includes(this.type) === true
		},
		canUpdateGlobal() {
			return (
				this.globalBlock?.missing !== true &&
				this.globalBlock?.lock?.isLocked !== true &&
				this.globalBlock?.permissions?.update !== false
			)
		},
		isDisabled() {
			return this.disabled === true || this.fieldset.disabled === true
		},
		isContentDisabled() {
			return this.isDisabled || (this.globalBlock !== null && this.canUpdateGlobal === false)
		},
		isEditable() {
			return (this.globalBlock === null || this.canUpdateGlobal) && this.fieldset.editable !== false
		},
		isGlobalBlockEditor() {
			return this.endpoints?.model?.replace(/^\//, "").startsWith("pages/_global+") === true
		},
		lockedBy() {
			const email = this.globalBlock?.lock?.user?.email
			return email ? this.$t("global-blocks.lockedBy", { email }) : this.$t("global-blocks.locked")
		},
		listeners() {
			return {
				append: (event) => this.$emit("append", event),
				chooseToAppend: (event) => this.$emit("chooseToAppend", event),
				chooseToConvert: (event) => this.$emit("chooseToConvert", event),
				chooseToPrepend: (event) => this.$emit("chooseToPrepend", event),
				close: () => this.$emit("close"),
				copy: () => this.$emit("copy"),
				duplicate: () => this.$emit("duplicate"),
				focus: () => this.$emit("focus"),
				hide: () => this.$emit("hide"),
				merge: () => this.$emit("merge"),
				open: (tab) => this.open(tab),
				paste: () => this.$emit("paste"),
				prepend: (event) => this.$emit("prepend", event),
				remove: () => this.removeBlock(),
				removeSelected: () => this.$emit("removeSelected"),
				show: () => this.$emit("show"),
				sortDown: () => this.$emit("sortDown"),
				sortUp: () => this.$emit("sortUp"),
				split: (event) => this.$emit("split", event),
				update: (event) => this.onInput(event)
			}
		},
		listenersForOptions() {
			return {
				...this.listeners,
				detachGlobal: this.detachGlobal,
				makeGlobal: this.makeGlobal,
				split: () => this.$refs.editor.split(),
				open: () => {
					if (typeof this.$refs.editor.open === "function") {
						this.$refs.editor.open()
					} else {
						this.open()
					}
				}
			}
		},
		tabs() {
			const tabs = this.$helper.object.clone(this.fieldset.tabs ?? {})
			const endpoint = this.globalBlock?.fieldEndpoint ?? this.endpoints.field

			for (const [tabName, tab] of Object.entries(tabs)) {
				for (const [fieldName] of Object.entries(tab.fields ?? {})) {
					tabs[tabName].fields[fieldName].section = this.name
					tabs[tabName].fields[fieldName].endpoints = {
						field: `${endpoint}/fieldsets/${this.type}/fields/${fieldName}`,
						section: this.endpoints.section,
						model: this.globalBlock?.apiUrl ?? this.endpoints.model
					}
				}
			}

			return tabs
		}
	},
	methods: {
		backspace(event) {
			if (event.target.matches("[contenteditable], input, textarea")) {
				return false
			}

			event.preventDefault()
			this.removeBlock()
		},
		blocksOwner() {
			let component = this.$parent
			while (component && typeof component.makeGlobal !== "function") {
				component = component.$parent
			}
			return component
		},
		detachGlobal() {
			this.blocksOwner()?.detachGlobal({
				content: this.content,
				id: this.id,
				isHidden: this.isHidden,
				type: this.type
			})
		},
		makeGlobal() {
			this.blocksOwner()?.makeGlobal({
				content: this.content,
				id: this.id,
				isHidden: this.isHidden,
				type: this.type
			})
		},
		removeBlock() {
			if (this.isGlobalBlockEditor === false) {
				this.remove()
			}
		},
		renameGlobal() {
			if (this.canUpdateGlobal === false) {
				return
			}

			this.$panel.dialog.open({
				component: "k-form-dialog",
				props: {
					fields: {
						title: {
							icon: "title",
							label: this.$t("title"),
							preselect: true,
							required: true,
							type: "text"
						}
					},
					submitButton: this.$t("rename"),
					value: {
						title: this.globalBlock.title
					}
				},
				on: {
					submit: async ({ title }) => {
						if ((await this.blocksOwner()?.renameGlobalBlock(this.globalBlock, title)) !== false) {
							this.$panel.dialog.close()
						}
					}
				}
			})
		},
		onInput(value) {
			if (!this.globalBlock) {
				this.$emit("update", value)
				return
			}

			const content = { ...value }
			delete content._global
			const owner = this.blocksOwner()

			// update all placements at once, the global block is saved in order in the background
			owner?.updateGlobalBlock(content, this.globalBlock)
			owner?.queueGlobalSave(this.globalBlock, {
				block: [
					{
						content,
						id: this.globalBlock.blockId,
						isHidden: false,
						type: this.type
					}
				]
			})
		}
	}
}
</script>
