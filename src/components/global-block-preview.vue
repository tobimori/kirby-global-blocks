<template>
	<div :style="{ '--scale': scale }" class="k-frame k-global-block-preview" inert>
		<iframe v-if="scale" :src="src" loading="lazy" tabindex="-1" title="" />
	</div>
</template>

<script>
// the page renders at a desktop width and is scaled down to the frame width
const viewportWidth = 1280

export default {
	props: {
		src: String
	},
	data() {
		return {
			scale: null
		}
	},
	mounted() {
		this.observer = new ResizeObserver(([entry]) => {
			this.scale = entry.contentRect.width / viewportWidth
		})
		this.observer.observe(this.$el)
	},
	destroyed() {
		this.observer.disconnect()
	}
}
</script>
