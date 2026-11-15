/**
 * The blocks found under src/ (wp-scripts' default), plus the editor panel and the booking experience, which are no
 * blocks.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const blocks = typeof defaultConfig.entry === 'function' ? defaultConfig.entry() : defaultConfig.entry;

module.exports = {
	...defaultConfig,
	entry: { ...blocks, 'editor-panel': './src/editor-panel.js', experience: './src/experience/index.js' },
};
