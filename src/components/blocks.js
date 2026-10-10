// shared by all blocks fields of the view, so that all placements
// of one global block use one save queue and one change entry
const changedGlobalBlocks = {}
const globalSaveQueues = {}

export default {
	extends: "k-blocks",
	inject: {
		globalBlocksContext: {
			default: () => () => false
		},
		globalBlocksCreatableContext: {
			default: () => () => []
		}
	},
	props: {
		globalBlocks: {
			type: Boolean,
			default: null
		},
		globalBlocksCreatable: {
			type: Array,
			default: null
		}
	},
	computed: {
		creatableGlobalTypes() {
			if (Array.isArray(this.globalBlocksCreatable)) {
				return this.globalBlocksCreatable
			}

			return this.globalBlocksCreatableContext?.() ?? []
		},
		isGlobalBlocksEnabled() {
			// layout columns get the option from the layout field
			return (this.globalBlocks ?? this.globalBlocksContext?.()) === true
		}
	},
	created() {
		this._syncGlobalBlock = (event) => this.syncGlobalBlock(event)
		this._unlockGlobalBlocks = () => this.unlockGlobalBlocks()
		this._publishGlobalBlocks = ({ api }) => {
			if (this.isParentApi(api)) {
				this.writeCreatedGlobalBlocks([])
				this.applyGlobalChanges("publish")
			}
		}
		this._discardGlobalBlocks = ({ api }) => {
			if (this.isParentApi(api)) {
				this.applyGlobalChanges("discard")
				this.removeCreatedGlobalBlocks()
			}
		}

		this.restoreGlobalChanges()
		this.$events.on("content.publish", this._publishGlobalBlocks)
		this.$events.on("content.discard", this._discardGlobalBlocks)
		this.$events.on("global-blocks.update", this._syncGlobalBlock)
		window.addEventListener("beforeunload", this._unlockGlobalBlocks)
	},
	destroyed() {
		this.$events.off("content.publish", this._publishGlobalBlocks)
		this.$events.off("content.discard", this._discardGlobalBlocks)
		this.$events.off("global-blocks.update", this._syncGlobalBlock)
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
			// every blocks field of the page gets the event, the first one takes the entries
			const entries = this.changedGlobalEntries()
			if (entries.length === 0) {
				return
			}

			for (const entry of entries) {
				delete changedGlobalBlocks[entry.uuid]
			}

			// pending saves must not recreate discarded changes or miss the publish
			for (const entry of entries) {
				const queue = globalSaveQueues[entry.uuid]
				if (queue && action === "discard") {
					queue.pending = null
				}
				await queue?.running
			}

			const results = await Promise.allSettled(
				entries.map((entry) => this.$api.post(`${entry.apiUrl}/changes/${action}`, {}, { silent: true }))
			)

			if (results.some((result) => result.status === "rejected")) {
				this.$panel.notification.error(this.$t("error"))
			}
		},
		changedGlobalEntries() {
			return Object.values(changedGlobalBlocks).filter((entry) => entry.host === this.hostApi())
		},
		async choose(index) {
			// loaded on demand, so the list is current and not part of the field props
			const globalBlocks = this.isGlobalBlocksEnabled
				? await this.$api.get(`${this.endpoints.field}/global-blocks`)
				: []

			if (Object.keys(this.fieldsets).length === 1 && globalBlocks.length === 0) {
				return this.add(Object.values(this.fieldsets)[0].type, index)
			}

			this.$panel.dialog.open({
				component: "k-global-block-selector",
				props: {
					fieldsetGroups: this.fieldsetGroups,
					fieldsets: this.fieldsets,
					globalBlocks
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
		hostApi() {
			return this.endpoints?.model?.replace(/^\//, "")
		},
		isParentApi(api) {
			return api?.replace(/^\//, "") === this.hostApi()
		},
		async makeGlobal(block) {
			const fields = {
				title: {
					label: this.$t("global-blocks.titleLabel"),
					type: "text"
				},
				description: {
					buttons: false,
					label: this.$t("global-blocks.descriptionLabel"),
					type: "textarea"
				}
			}

			// like the duplicate dialog of pages, files are only copied on request
			const { files } = await this.$api.post(`${this.endpoints.field}/global-blocks/files`, { block })
			if (files.length > 0) {
				fields.copyFiles = {
					help: this.$t("global-blocks.copyFilesHelp", { files: files.join(", ") }),
					label: this.$t("global-blocks.copyFiles"),
					type: "toggle"
				}
			}

			this.$panel.dialog.open({
				component: "k-form-dialog",
				props: {
					fields,
					submitButton: this.$t("global-blocks.createFromBlock"),
					value: {
						copyFiles: false
					}
				},
				on: {
					submit: async (value) => {
						const replacement = await this.$api.post(`${this.endpoints.field}/global-blocks`, { ...value, block })
						const index = this.findIndex(block.id)
						this.blocks.splice(index, 1, replacement)
						const global = replacement.content?._global
						if (global) {
							this.writeCreatedGlobalBlocks([...this.readCreatedGlobalBlocks(), global.apiUrl])
						}
						this.save()
						this.$panel.dialog.close()
					}
				}
			})
		},
		/**
		 * Saves values to the changes version of a global block.
		 * Only one request runs at a time, new values are merged into the next request.
		 * Resolves with `false` if the save failed.
		 */
		queueGlobalSave(global, values) {
			const queue = (globalSaveQueues[global.uuid] ??= { pending: null, running: null })
			queue.pending = { ...queue.pending, ...values }
			queue.running ??= this.runGlobalSaves(global, queue)
			return queue.running
		},
		renameGlobalBlock(global, title) {
			const { revision } = this.trackGlobalBlock(global)
			this.$events.emit("global-blocks.update", { revision, title, uuid: global.uuid })
			return this.queueGlobalSave(global, { title })
		},
		// global blocks created from blocks of this field since the last save,
		// kept in the session storage so that a reload does not forget them
		createdGlobalBlocksKey() {
			return `kirby-global-blocks.created:${this.endpoints?.field}`
		},
		readCreatedGlobalBlocks() {
			try {
				return JSON.parse(sessionStorage.getItem(this.createdGlobalBlocksKey())) ?? []
			} catch {
				return []
			}
		},
		removeCreatedGlobalBlocks() {
			// the server keeps global blocks that are still used, for example on other pages
			for (const apiUrl of this.readCreatedGlobalBlocks()) {
				this.$api.delete(apiUrl, {}, { silent: true }).catch(() => {})
			}

			this.writeCreatedGlobalBlocks([])
		},
		restoreGlobalChanges() {
			for (const block of this.blocks) {
				const global = block.content?._global
				if (global?.hasChanges === true && global.lock?.isLocked !== true) {
					this.trackGlobalBlock(global, false)
				}
			}
		},
		async runGlobalSaves(global, queue) {
			try {
				while (queue.pending) {
					const values = queue.pending
					queue.pending = null
					await this.$api.post(`${global.apiUrl}/changes/save`, values, { silent: true })
				}

				return true
			} catch (error) {
				queue.pending = null

				if (error?.details?.isLocked) {
					this.$panel.view.reload()
				} else {
					this.$panel.notification.error(error)
				}

				return false
			} finally {
				queue.running = null
			}
		},
		/**
		 * Updates all placements of a global block in this field.
		 * Every blocks field of the page gets the event.
		 */
		syncGlobalBlock({ content, revision, title, uuid }) {
			let changed = false

			for (const [index, block] of this.blocks.entries()) {
				const global = block.content?._global
				if (global?.uuid !== uuid) {
					continue
				}

				const next = { ...global, hasChanges: true, revision }
				if (title !== undefined) {
					next.title = title
				}

				const source = content ?? block.content
				this.$set(this.blocks[index], "content", { ...this.$helper.object.clone(source), _global: next })
				changed = true
			}

			if (changed) {
				this.save()
			}
		},
		/**
		 * Marks a global block as changed on this page. A new revision
		 * changes the stored references, so that the page can be saved.
		 */
		trackGlobalBlock(global, revise = true) {
			const host = this.hostApi()
			const entry = changedGlobalBlocks[global.uuid]
			if (entry?.host === host) {
				// restored entries can lack a revision, an edit needs one for the Save button
				entry.revision ??= revise ? this.$helper.uuid() : null
				return entry
			}

			return (changedGlobalBlocks[global.uuid] = {
				apiUrl: global.apiUrl,
				host,
				revision: revise ? this.$helper.uuid() : (global.revision ?? null),
				uuid: global.uuid
			})
		},
		updateGlobalBlock(content, global) {
			const { revision } = this.trackGlobalBlock(global)
			this.$events.emit("global-blocks.update", { content, revision, uuid: global.uuid })
		},
		writeCreatedGlobalBlocks(list) {
			sessionStorage.setItem(this.createdGlobalBlocksKey(), JSON.stringify(list))
		},
		unlockGlobalBlocks() {
			for (const entry of this.changedGlobalEntries()) {
				this.$panel.content.unlock({
					api: `/${entry.apiUrl.replace(/^\//, "")}`,
					language: this.$panel.language.code
				})
			}
		}
	}
}
