export default {
	extends: "k-blocks",
	inject: {
		globalBlocksContext: {
			default: () => false
		}
	},
	props: {
		globalBlocks: {
			type: [Array, Boolean],
			default: false
		}
	},
	data() {
		return {
			changedGlobalBlocks: {},
			localGlobalBlocks: null
		}
	},
	computed: {
		globalBlocksValue() {
			if (Array.isArray(this.localGlobalBlocks)) {
				return this.localGlobalBlocks
			}

			if (Array.isArray(this.globalBlocks)) {
				return this.globalBlocks
			}

			return this.globalBlocksContext?.()
		},
		availableGlobalBlocks() {
			return Array.isArray(this.globalBlocksValue) ? this.globalBlocksValue : []
		},
		isGlobalBlocksEnabled() {
			return Array.isArray(this.globalBlocksValue)
		}
	},
	created() {
		this._unlockGlobalBlocks = () => this.unlockGlobalBlocks()
		this._publishGlobalBlocks = ({ api }) => {
			if (this.isParentApi(api)) {
				this.applyGlobalChanges("publish")
			}
		}
		this._discardGlobalBlocks = ({ api }) => {
			if (this.isParentApi(api)) {
				this.applyGlobalChanges("discard")
			}
		}

		this.restoreGlobalChanges()
		this.$events.on("content.publish", this._publishGlobalBlocks)
		this.$events.on("content.discard", this._discardGlobalBlocks)
		window.addEventListener("beforeunload", this._unlockGlobalBlocks)
	},
	destroyed() {
		this.$events.off("content.publish", this._publishGlobalBlocks)
		this.$events.off("content.discard", this._discardGlobalBlocks)
		window.removeEventListener("beforeunload", this._unlockGlobalBlocks)
		this.unlockGlobalBlocks()
	},
	methods: {
		async addGlobal(uuid, index) {
			const block = await this.$api.get(`${this.endpoints.field}/global-blocks/${uuid}`)
			this.blocks.splice(index, 0, block)
			this.save()

			await this.$nextTick()
			this.focus(block)
		},
		async applyGlobalChanges(action) {
			const entries = Object.values(this.changedGlobalBlocks)
			if (entries.length === 0) {
				return
			}

			const results = await Promise.allSettled(
				entries.map((entry) => this.$api.post(`${entry.apiUrl}/changes/${action}`, {}, { silent: true }))
			)

			if (results.some((result) => result.status === "rejected")) {
				this.$panel.notification.error(this.$t("error"))
				return
			}

			this.changedGlobalBlocks = {}
		},
		choose(index) {
			const globalFieldsets = {}
			const globalKeys = []
			for (const global of this.availableGlobalBlocks) {
				const key = `global:${global.uuid}`
				globalKeys.push(key)
				globalFieldsets[key] = {
					icon: "global-block",
					name: global.title,
					type: key
				}
			}

			const regularGroups = this.fieldsetGroups ?? {
				blocks: {
					label: this.$t("global-blocks.blockTypes"),
					sets: Object.keys(this.fieldsets)
				}
			}
			const groups = globalKeys.length
				? {
						globalBlocks: {
							label: this.$t("global-blocks.globalPlural"),
							sets: globalKeys
						},
						...regularGroups
					}
				: regularGroups

			if (Object.keys(this.fieldsets).length === 1 && globalKeys.length === 0) {
				return this.add(Object.values(this.fieldsets)[0].type, index)
			}

			this.$panel.dialog.open({
				component: "k-block-selector",
				props: {
					fieldsetGroups: groups,
					fieldsets: { ...globalFieldsets, ...this.fieldsets }
				},
				on: {
					submit: (type) => {
						if (type.startsWith("global:")) {
							this.addGlobal(type.slice(7), index)
						} else {
							this.add(type, index)
						}
						this.$panel.dialog.close()
					},
					paste: (event) => this.paste(event, index)
				}
			})
		},
		async detachGlobal(block) {
			const index = this.findIndex(block.id)
			if (index === -1) {
				return
			}

			const copy = this.$helper.object.clone(block)
			delete copy.content._global
			this.blocks.splice(index, 1, copy)
			this.save()
		},
		isParentApi(api) {
			const model = this.endpoints?.model?.replace(/^\//, "")
			return api?.replace(/^\//, "") === model
		},
		makeGlobal(block) {
			this.$panel.dialog.open({
				component: "k-form-dialog",
				props: {
					fields: {
						title: {
							label: this.$t("global-blocks.titleLabel"),
							type: "text"
						},
						description: {
							buttons: false,
							label: this.$t("global-blocks.descriptionLabel"),
							type: "textarea"
						}
					},
					submitButton: this.$t("global-blocks.createFromBlock")
				},
				on: {
					submit: async (value) => {
						const replacement = await this.$api.post(`${this.endpoints.field}/global-blocks`, { ...value, block })
						const index = this.findIndex(block.id)
						this.blocks.splice(index, 1, replacement)
						const global = replacement.content?._global
						if (global && this.availableGlobalBlocks.some((item) => item.uuid === global.uuid) === false) {
							this.localGlobalBlocks = [...this.availableGlobalBlocks, global]
						}
						this.save()
						this.$panel.dialog.close()
					}
				}
			})
		},
		async renameGlobalBlock(global, title) {
			this.trackGlobalBlock(global)
			this.$set(global, "title", title)

			for (const block of this.blocks) {
				if (block.content?._global?.uuid === global.uuid) {
					this.$set(block.content._global, "title", title)
				}
			}

			for (const option of this.availableGlobalBlocks) {
				if (option.uuid === global.uuid) {
					this.$set(option, "title", title)
				}
			}

			return this.saveParent()
		},
		restoreGlobalChanges() {
			for (const block of this.blocks) {
				const global = block.content?._global
				if (global?.hasChanges === true && global.lock?.isLocked !== true) {
					this.trackGlobalBlock(global, false)
				}
			}
		},
		async saveParent() {
			this.save()
			await this.$nextTick()

			return this.$panel.content.update({}, {
				api: `/${this.endpoints.model.replace(/^\//, "")}`,
				language: this.$panel.language.code
			})
		},
		trackGlobalBlock(global, revise = true) {
			if (revise && this.changedGlobalBlocks[global.uuid] === undefined) {
				this.$set(global, "revision", this.$helper.uuid())
			}

			this.$set(global, "hasChanges", true)
			this.$set(this.changedGlobalBlocks, global.uuid, global)
		},
		async updateGlobalBlock(placementId, content, global) {
			const index = this.findIndex(placementId)
			if (index === -1) {
				return false
			}

			this.trackGlobalBlock(global)
			this.$set(this.blocks[index], "content", { ...content, _global: global })
			return this.saveParent()
		},
		unlockGlobalBlocks() {
			for (const global of Object.values(this.changedGlobalBlocks)) {
				this.$panel.content.unlock({
					api: `/${global.apiUrl.replace(/^\//, "")}`,
					language: this.$panel.language.code
				})
			}
		}
	}
}
