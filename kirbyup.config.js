import { defineConfig } from "kirbyup/config"

export default defineConfig({
	vite: {
		server: {
			watch: {
				// the playground links this plugin, the watcher would follow the link back into the repository forever
				ignored: ["**/.playground/**", "**/.repos/**", "**/vendor/**"]
			}
		}
	}
})
