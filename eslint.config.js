import js from "@eslint/js"
import unicorn from "eslint-plugin-unicorn"
import pluginVue from "eslint-plugin-vue"
import globals from "globals"

export default [
	js.configs.recommended,
	...pluginVue.configs["flat/essential"],
	{
		files: ["src/**/*.{js,vue}"],
		languageOptions: {
			globals: {
				...globals.browser,
				panel: "readonly"
			}
		},
		plugins: {
			unicorn
		},
		rules: {
			"unicorn/filename-case": ["error", { case: "kebabCase" }],
			"vue/multi-word-component-names": "off"
		}
	}
]
