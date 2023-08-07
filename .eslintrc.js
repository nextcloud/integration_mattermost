module.exports = {
	globals: {
		appVersion: true,
	},
	parserOptions: {
		requireConfigFile: false,
	},
	extends: [
		'@nextcloud',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'jsdoc/tag-lines': 'off',
		'vue/first-attribute-linebreak': 'off',
		'import/extensions': 'off',
		'n/no-unpublished-import': ['error', {
			convertPath: {
				'src/**/*.vue': ['^src/(.+?)\\.vue$', 'js/$1.js'],
			},
		}],
	},
}
