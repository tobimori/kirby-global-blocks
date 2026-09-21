import "./index.css"
import BlockOptions from "./components/block-options.js"
import Block from "./components/block.vue"
import BlocksField from "./components/blocks-field.js"
import Blocks from "./components/blocks.js"
import GlobalBlocksView from "./components/global-blocks-view.vue"
import LayoutField from "./components/layout-field.js"

panel.plugin("tobimori/global-blocks", {
	components: {
		"k-block-options": BlockOptions,
		"k-block": Block,
		"k-blocks": Blocks,
		"k-blocks-field": BlocksField,
		"k-global-blocks-view": GlobalBlocksView,
		"k-layout-field": LayoutField
	},
	icons: {
		"global-block": `<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-3.1a15.7 15.7 0 0 0-1.2-3.07A8.04 8.04 0 0 1 18.93 8ZM12 4c.83 1.2 1.47 2.54 1.88 4h-3.76A13.73 13.73 0 0 1 12 4ZM4.26 14a7.8 7.8 0 0 1 0-4h3.42a16.52 16.52 0 0 0 0 4H4.26Zm.81 2h3.1c.28 1.08.68 2.1 1.2 3.07A8.04 8.04 0 0 1 5.07 16Zm3.1-8h-3.1a8.04 8.04 0 0 1 4.3-3.07A15.7 15.7 0 0 0 8.17 8ZM12 20a13.73 13.73 0 0 1-1.88-4h3.76A13.73 13.73 0 0 1 12 20Zm2.32-6H9.68a14.52 14.52 0 0 1 0-4h4.64a14.52 14.52 0 0 1 0 4Zm.31 5.07c.52-.97.92-1.99 1.2-3.07h3.1a8.04 8.04 0 0 1-4.3 3.07ZM16.32 14a16.52 16.52 0 0 0 0-4h3.42a7.8 7.8 0 0 1 0 4h-3.42Z"/>`
	}
})
