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
	}
}
