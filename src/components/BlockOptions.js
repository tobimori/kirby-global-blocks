export default {
	extends: "k-block-options",
	props: {
		globalBlock: {
			type: Object,
			default: null
		},
		globalBlocksEnabled: Boolean
	},
	emits: ["detachGlobal", "makeGlobal"],
	computed: {
		buttons() {
			if (this.isBatched) {
				return [
					{
						icon: "template",
						title: this.$t("copy"),
						click: () => this.$emit("copy")
					},
					{
						when: this.isMergable,
						icon: "merge",
						title: this.$t("merge"),
						click: () => this.$emit("merge")
					},
					{
						icon: "trash",
						title: this.$t("remove"),
						click: () => this.$emit("removeSelected")
					}
				]
			}

			const transform = this.globalBlock?.missing
				? []
				: this.globalBlock
					? [
							{
								when: this.isEditable,
								icon: "edit",
								label: this.$t("global-blocks.edit"),
								click: () => this.$emit("open")
							},
							{
								icon: "unlock",
								label: this.$t("global-blocks.detach"),
								click: () => this.$emit("detachGlobal")
							}
						]
					: [
							{
								when: this.isEditable,
								icon: "edit",
								label: this.$t("edit"),
								click: () => this.$emit("open")
							},
							{
								icon: "refresh",
								label: this.$t("field.blocks.changeType"),
								click: () => this.$emit("chooseToConvert")
							},
							{
								when: this.isSplitable,
								icon: "split",
								label: this.$t("split"),
								click: () => this.$emit("split")
							},
							{
								when: this.globalBlocksEnabled,
								icon: "global-block",
								label: this.$t("global-blocks.createFromBlock"),
								click: () => this.$emit("makeGlobal")
							}
						]

			return [
				{
					when: this.isEditable,
					icon: "edit",
					title: this.$t("edit"),
					click: () => this.$emit("open")
				},
				{
					icon: "add",
					title: this.$t("insert.after"),
					disabled: this.isFull,
					click: () => this.$emit("chooseToAppend")
				},
				{
					icon: "trash",
					title: this.$t("delete"),
					click: () => this.$emit("remove")
				},
				{
					icon: "sort",
					title: this.$t("sort.drag"),
					class: "k-sort-handle",
					key: (event) => this.sort(event)
				},
				{
					icon: "dots",
					title: this.$t("more"),
					dropdown: [
						{
							icon: "angle-up",
							label: this.$t("insert.before"),
							disabled: this.isFull,
							click: () => this.$emit("chooseToPrepend")
						},
						{
							icon: "angle-down",
							label: this.$t("insert.after"),
							disabled: this.isFull,
							click: () => this.$emit("chooseToAppend")
						},
						"-",
						...transform,
						"-",
						{
							icon: "template",
							label: this.$t("copy"),
							click: () => this.$emit("copy")
						},
						{
							icon: "download",
							label: this.$t("paste.after"),
							disabled: this.isFull,
							click: () => this.$emit("paste")
						},
						"-",
						{
							icon: this.isHidden ? "preview" : "hidden",
							label: this.isHidden ? this.$t("show") : this.$t("hide"),
							click: () => this.$emit(this.isHidden ? "show" : "hide")
						},
						{
							icon: "copy",
							label: this.$t("duplicate"),
							click: () => this.$emit("duplicate")
						},
						"-",
						{
							icon: "trash",
							label: this.$t("delete"),
							click: () => this.$emit("remove")
						}
					]
				}
			]
		}
	}
}
