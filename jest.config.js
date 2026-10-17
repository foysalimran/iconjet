/**
 * Jest config: the @wordpress/scripts defaults, plus Babel for the
 * dependencies of @wordpress/components that ship only as ES modules
 * (uuid, and .mjs builds of @wordpress packages).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

const babel = path.join(
	path.dirname( require.resolve( '@wordpress/scripts/package.json' ) ),
	'config/babel-transform'
);

module.exports = {
	...defaultConfig,
	transform: {
		'\\.[jt]sx?$': babel,
		'\\.mjs$': babel,
	},
	// Skip node_modules, except any path that ends in uuid or a @wordpress package.
	transformIgnorePatterns: [
		'/node_modules/(?!.*/node_modules/(uuid|@wordpress)/)(?!(uuid|@wordpress)/)',
	],
	modulePathIgnorePatterns: [ '<rootDir>/dist/' ],
};
