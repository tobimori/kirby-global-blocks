export default {
	extends: "k-layout-field",
	props: {
		globalBlocks: {
			type: Boolean,
			default: false
		},
		globalBlocksCreatable: {
			type: Array,
			default: () => []
		}
	},
	provide() {
		return {
			globalBlocksContext: () => this.globalBlocks,
			globalBlocksCreatableContext: () => this.globalBlocksCreatable
		}
	}
}
