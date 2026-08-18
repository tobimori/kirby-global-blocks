export default {
	extends: "k-layout-field",
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
	}
}
