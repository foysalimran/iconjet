/**
 * Extends the @wordpress/scripts config: blocks are found through their
 * block.json files; the admin app is an extra entry.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const defaultEntry =
	typeof defaultConfig.entry === 'function'
		? defaultConfig.entry()
		: defaultConfig.entry;

module.exports = {
	...defaultConfig,
	entry: {
		...defaultEntry,
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
		'elementor/index': path.resolve( __dirname, 'src/elementor/index.js' ),
	},
};
