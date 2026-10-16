/**
 * Builds the Iconjet pack data from the original npm packages.
 *
 * Output:
 *   assets/packs/{pack}.json   One file per pack (see PACK FORMAT below).
 *   assets/packs/index.json    Pack list with label, count, license, categories.
 *   assets/social/platforms.json
 *   licenses/{pack}-LICENSE.txt
 *
 * PACK FORMAT
 *   {
 *     "id": "lucide", "label": "Lucide", "license": "ISC", "count": 1234,
 *     "attrs": { "fill": "none", "stroke": "currentColor", ... },  // root <svg> attributes shared by every icon
 *     "categories": { "arrows": "Arrows", ... },
 *     "icons": {
 *       "heart": {
 *         "v": "0 0 24 24",          // viewBox
 *         "b": "<path d=\"...\"/>",  // inner SVG markup
 *         "t": [ "love", "like" ],   // search tags (optional)
 *         "c": [ "arrows" ],         // category ids (optional)
 *         "a": { "stroke-width": "1.5" } // root attribute overrides (optional, rare)
 *       }
 *     }
 *   }
 *
 * Icon references are "pack:name" everywhere, e.g. "lucide:heart".
 *
 * Usage: npm install && npm run build:icons
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { optimize } from 'svgo';
import socialPlatforms from './social-platforms.mjs';

const require = createRequire( import.meta.url );
const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const NM = path.join( ROOT, 'node_modules' );
const OUT_PACKS = path.join( ROOT, 'assets/packs' );
const OUT_SOCIAL = path.join( ROOT, 'assets/social' );
const OUT_LICENSES = path.join( ROOT, 'licenses' );

const STROKE_ATTRS = {
	fill: 'none',
	stroke: 'currentColor',
	'stroke-width': '2',
	'stroke-linecap': 'round',
	'stroke-linejoin': 'round',
};
const FILL_ATTRS = { fill: 'currentColor' };

// Root attributes that describe how an icon is painted. Everything else on
// the root <svg> (class, width, height, xmlns, role...) is dropped.
const PAINT_ATTRS = [ 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule' ];

const MAX_TAGS = 12;

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

const pkgVersion = ( name ) => require( path.join( NM, name, 'package.json' ) ).version;

const listSvgs = ( dir ) =>
	fs
		.readdirSync( dir )
		.filter( ( f ) => f.endsWith( '.svg' ) )
		.sort()
		.map( ( f ) => ( { name: f.slice( 0, -4 ), file: path.join( dir, f ) } ) );

const slugifyCategory = ( label ) =>
	String( label )
		.toLowerCase()
		.replace( /&/g, 'and' )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );

/**
 * Normalize tags: lowercase strings, unique, drop words already in the icon
 * name (search matches the name anyway), cap the count to keep packs small.
 */
function cleanTags( name, tags ) {
	const nameWords = new Set( name.split( /[-_]/ ) );
	const out = [];
	for ( const raw of tags || [] ) {
		const tag = String( raw ).toLowerCase().trim();
		if ( ! tag || nameWords.has( tag ) || tag === name || out.includes( tag ) ) {
			continue;
		}
		out.push( tag );
		if ( out.length >= MAX_TAGS ) {
			break;
		}
	}
	return out;
}

const svgoConfig = {
	multipass: true,
	floatPrecision: 3,
	plugins: [
		{
			name: 'preset-default',
			params: {
				overrides: {
					// All bundled packs are monochrome: turn any hardcoded color
					// into currentColor. "none" is left alone, so stroke packs
					// keep fill="none".
					convertColors: { currentColor: true },
					// Never merge/move root paint attributes into children; we
					// read them off the root below.
					moveElemsAttrsToGroup: false,
					moveGroupAttrsToElems: false,
					// Also strip "<!--! ... -->" legal comments. Required
					// attributions are kept in the pack data and licenses/.
					removeComments: { preservePatterns: false },
				},
			},
		},
		'removeDimensions',
		'removeTitle',
		'removeDesc',
		{ name: 'removeAttrs', params: { attrs: [ 'class', 'role', 'data-.*', 'aria-.*' ] } },
	],
};

function parseAttrs( str ) {
	const attrs = {};
	for ( const m of str.matchAll( /([\w:-]+)="([^"]*)"/g ) ) {
		attrs[ m[ 1 ] ] = m[ 2 ];
	}
	return attrs;
}

/**
 * Optimize one SVG and split it into viewBox, root paint attributes and inner markup.
 *
 * @param {string} source     Raw SVG.
 * @param {Object} packAttrs  Pack default root attributes.
 * @return {{v:string,b:string,a?:Object}|null}
 */
function processSvg( source, packAttrs ) {
	const { data } = optimize( source, svgoConfig );
	const m = data.match( /^<svg([^>]*)>([\s\S]*)<\/svg>$/ );
	if ( ! m ) {
		return null;
	}
	const root = parseAttrs( m[ 1 ] );
	let body = m[ 2 ];

	if ( ! root.viewBox || ! body ) {
		return null;
	}

	// Children repeating fill="currentColor" are redundant when the root already
	// paints with currentColor.
	if ( packAttrs.fill === 'currentColor' ) {
		body = body.replace( / fill="currentColor"/g, '' );
	}
	if ( packAttrs.stroke === 'currentColor' ) {
		body = body.replace( / stroke="currentColor"/g, '' );
	}

	const icon = { v: root.viewBox, b: body };

	const overrides = {};
	for ( const attr of PAINT_ATTRS ) {
		if ( attr in root && root[ attr ] !== packAttrs[ attr ] ) {
			overrides[ attr ] = root[ attr ] === 'currentcolor' ? 'currentColor' : root[ attr ];
		}
	}
	if ( Object.keys( overrides ).length ) {
		icon.a = overrides;
	}
	return icon;
}

/* ------------------------------------------------------------------ */
/* Pack readers                                                        */
/* Each returns [ { name, file, tags?, categories?, pre? } ]           */
/* ------------------------------------------------------------------ */

function readLucide() {
	const dir = path.join( NM, 'lucide-static/icons' );
	const tags = require( path.join( NM, 'lucide-static/tags.json' ) );
	return {
		categories: {},
		items: listSvgs( dir ).map( ( i ) => ( { ...i, tags: tags[ i.name ] } ) ),
	};
}

function readTabler() {
	const dir = path.join( NM, '@tabler/icons/icons/outline' );
	const meta = require( path.join( NM, '@tabler/icons/icons.json' ) );
	const categories = {};
	const items = listSvgs( dir ).map( ( i ) => {
		const m = meta[ i.name ] || {};
		const cats = [];
		if ( m.category ) {
			const id = slugifyCategory( m.category );
			categories[ id ] = m.category;
			cats.push( id );
		}
		return {
			...i,
			tags: m.tags,
			categories: cats,
			// Tabler ships an invisible 24x24 bounding-box path in every icon.
			pre: ( svg ) => svg.replace( /<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/, '' ),
		};
	} );
	return { categories, items };
}

async function readPhosphor() {
	const dir = path.join( NM, '@phosphor-icons/core/assets/regular' );
	const { icons } = await import( path.join( NM, '@phosphor-icons/core/dist/index.mjs' ) );
	const byName = new Map( icons.map( ( i ) => [ i.name, i ] ) );
	const categories = {};
	const items = listSvgs( dir ).map( ( i ) => {
		const m = byName.get( i.name ) || {};
		const cats = ( m.categories || [] ).map( ( label ) => {
			const id = slugifyCategory( label );
			categories[ id ] = label.replace( /\b\w/g, ( c ) => c.toUpperCase() );
			return id;
		} );
		return { ...i, tags: m.tags, categories: cats };
	} );
	return { categories, items };
}

function readBootstrap() {
	return { categories: {}, items: listSvgs( path.join( NM, 'bootstrap-icons/icons' ) ) };
}

function readMaterial() {
	const dir = path.join( NM, '@material-symbols/svg-400/outlined' );
	// Outlined style only: skip the "-fill" variants.
	const items = listSvgs( dir )
		.filter( ( i ) => ! i.name.endsWith( '-fill' ) )
		.map( ( i ) => ( { ...i, tags: i.name.split( '_' ) } ) );
	return { categories: {}, items };
}

function readSimple() {
	const data = require( path.join( NM, 'simple-icons/data/simple-icons.json' ) );
	const list = Array.isArray( data ) ? data : data.icons;
	const bySlug = new Map( list.map( ( i ) => [ i.slug, i ] ) );
	const items = listSvgs( path.join( NM, 'simple-icons/icons' ) ).map( ( i ) => {
		const m = bySlug.get( i.name ) || {};
		const aka = ( m.aliases && m.aliases.aka ) || [];
		return { ...i, tags: [ m.title, ...aka ], categories: [ 'brands' ] };
	} );
	return { categories: { brands: 'Brands' }, items };
}

/** Minimal parser for Font Awesome's categories.yml (fixed, simple structure). */
function readFaCategories() {
	const text = fs.readFileSync( path.join( NM, '@fortawesome/fontawesome-free/metadata/categories.yml' ), 'utf8' );
	const labels = {};
	const iconCats = {};
	let current = null;
	let inIcons = false;
	for ( const line of text.split( '\n' ) ) {
		let m;
		if ( ( m = line.match( /^([a-z0-9-]+):\s*$/ ) ) ) {
			current = m[ 1 ];
			inIcons = false;
		} else if ( current && ( m = line.match( /^ {2}label:\s*(.+)$/ ) ) ) {
			labels[ current ] = m[ 1 ].replace( /^['"]|['"]$/g, '' );
		} else if ( current && /^ {2}icons:/.test( line ) ) {
			inIcons = true;
		} else if ( current && inIcons && ( m = line.match( /^ {4}- (.+)$/ ) ) ) {
			const icon = m[ 1 ].trim();
			( iconCats[ icon ] ||= [] ).push( current );
		} else if ( /^ {2}\S/.test( line ) ) {
			inIcons = false;
		}
	}
	return { labels, iconCats };
}

function readFontAwesome() {
	const base = path.join( NM, '@fortawesome/fontawesome-free' );
	const families = require( path.join( base, 'metadata/icon-families.json' ) );
	const { labels, iconCats } = readFaCategories();
	const categories = {};
	const items = [];
	for ( const style of [ 'solid', 'regular', 'brands' ] ) {
		for ( const i of listSvgs( path.join( base, 'svgs', style ) ) ) {
			const meta = families[ i.name ] || {};
			const cats = style === 'brands' ? [ 'brands' ] : iconCats[ i.name ] || [];
			for ( const c of cats ) {
				categories[ c ] = c === 'brands' ? 'Brands' : labels[ c ] || c;
			}
			items.push( {
				name: `${ style }-${ i.name }`,
				file: i.file,
				tags: [ ...( ( meta.search && meta.search.terms ) || [] ), i.name.split( '-' ).join( ' ' ) ],
				categories: cats,
			} );
		}
	}
	return { categories, items };
}

/* ------------------------------------------------------------------ */
/* Pack definitions                                                    */
/* ------------------------------------------------------------------ */

const PACKS = [
	{
		id: 'lucide', label: 'Lucide', npm: 'lucide-static', style: 'Default',
		license: 'ISC', url: 'https://lucide.dev', licenseFile: 'LICENSE',
		attrs: STROKE_ATTRS, read: readLucide,
	},
	{
		id: 'tabler', label: 'Tabler Icons', npm: '@tabler/icons', style: 'Outline',
		license: 'MIT', url: 'https://tabler.io/icons', licenseFile: 'LICENSE',
		attrs: STROKE_ATTRS, read: readTabler,
	},
	{
		id: 'phosphor', label: 'Phosphor Icons', npm: '@phosphor-icons/core', style: 'Regular',
		license: 'MIT', url: 'https://phosphoricons.com', licenseFile: 'LICENSE',
		attrs: FILL_ATTRS, read: readPhosphor,
	},
	{
		id: 'bootstrap', label: 'Bootstrap Icons', npm: 'bootstrap-icons', style: 'Default',
		license: 'MIT', url: 'https://icons.getbootstrap.com', licenseFile: 'LICENSE',
		attrs: FILL_ATTRS, read: readBootstrap,
	},
	{
		id: 'material', label: 'Material Symbols', npm: '@material-symbols/svg-400', style: 'Outlined',
		license: 'Apache-2.0', url: 'https://fonts.google.com/icons', licenseFile: 'LICENSE',
		attrs: FILL_ATTRS, read: readMaterial,
	},
	{
		id: 'simple', label: 'Simple Icons', npm: 'simple-icons', style: 'Brands',
		license: 'CC0-1.0', url: 'https://simpleicons.org', licenseFile: 'LICENSE.md',
		extraFiles: [ 'DISCLAIMER.md' ],
		attrs: FILL_ATTRS, read: readSimple,
	},
	{
		id: 'fa', label: 'Font Awesome Free', npm: '@fortawesome/fontawesome-free', style: 'Solid, Regular, Brands',
		license: 'CC-BY-4.0', url: 'https://fontawesome.com/license/free', licenseFile: 'LICENSE.txt',
		// CC BY 4.0 requires attribution. SVGO strips the attribution comment
		// from each file, so it is carried in the pack data, licenses/ and readme.txt.
		attribution: 'Font Awesome Free by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0) Copyright Fonticons, Inc.',
		attrs: FILL_ATTRS, read: readFontAwesome,
	},
];

/* ------------------------------------------------------------------ */
/* Build                                                               */
/* ------------------------------------------------------------------ */

const fmt = ( bytes ) => ( bytes / 1024 / 1024 ).toFixed( 2 ).padStart( 6 ) + ' MB';
const deflated = ( buf ) => zlib.deflateRawSync( buf, { level: 9 } ).length;

function writeJson( file, data ) {
	const buf = Buffer.from( JSON.stringify( data ) );
	fs.writeFileSync( file, buf );
	return buf;
}

async function buildPack( def ) {
	const { categories, items } = await def.read();
	const icons = {};
	let skipped = 0;
	for ( const item of items ) {
		let source = fs.readFileSync( item.file, 'utf8' );
		if ( item.pre ) {
			source = item.pre( source );
		}
		const icon = processSvg( source, def.attrs );
		if ( ! icon ) {
			skipped++;
			continue;
		}
		const tags = cleanTags( item.name, item.tags );
		if ( tags.length ) {
			icon.t = tags;
		}
		if ( item.categories && item.categories.length ) {
			icon.c = item.categories;
		}
		icons[ item.name ] = icon;
	}

	const pack = {
		id: def.id,
		label: def.label,
		license: def.license,
		...( def.attribution ? { attribution: def.attribution } : {} ),
		version: pkgVersion( def.npm ),
		count: Object.keys( icons ).length,
		attrs: def.attrs,
		categories,
		icons,
	};
	const buf = writeJson( path.join( OUT_PACKS, `${ def.id }.json` ), pack );

	// License files.
	const licenseSrc = path.join( NM, def.npm, def.licenseFile );
	fs.copyFileSync( licenseSrc, path.join( OUT_LICENSES, `${ def.id }-LICENSE.txt` ) );
	for ( const extra of def.extraFiles || [] ) {
		fs.copyFileSync(
			path.join( NM, def.npm, extra ),
			path.join( OUT_LICENSES, `${ def.id }-${ extra.replace( /\.md$/, '.txt' ) }` )
		);
	}

	return { pack, raw: buf.length, zip: deflated( buf ), skipped };
}

function buildSocial( packsById ) {
	const simpleData = require( path.join( NM, 'simple-icons/data/simple-icons.json' ) );
	const simpleList = Array.isArray( simpleData ) ? simpleData : simpleData.icons;
	const hexBySlug = new Map( simpleList.map( ( i ) => [ i.slug, i.hex ] ) );

	const errors = [];
	const platforms = socialPlatforms.map( ( p ) => {
		const [ packId, name ] = p.icon.split( ':' );
		const pack = packsById[ packId ];
		const icon = pack && pack.icons[ name ];
		if ( ! icon ) {
			errors.push( `${ p.id }: icon ${ p.icon } not found` );
			return null;
		}
		const hex = ( p.color || ( packId === 'simple' ? hexBySlug.get( name ) : '' ) || '' ).toUpperCase();
		if ( ! /^[0-9A-F]{6}$/.test( hex ) ) {
			errors.push( `${ p.id }: missing brand color` );
			return null;
		}
		// Embed the SVG so social icons render without loading a whole pack.
		const attrs = { ...pack.attrs, ...( icon.a || {} ) };
		return {
			id: p.id,
			label: p.label,
			icon: p.icon,
			color: `#${ hex }`,
			fg: p.fg || contrastText( hex ),
			placeholder: p.placeholder,
			v: icon.v,
			b: icon.b,
			...( attrs.fill !== 'currentColor' ? { a: attrs } : {} ),
		};
	} );

	if ( errors.length ) {
		throw new Error( 'Social platforms:\n  ' + errors.join( '\n  ' ) );
	}
	const buf = writeJson( path.join( OUT_SOCIAL, 'platforms.json' ), { count: platforms.length, platforms } );
	return { count: platforms.length, raw: buf.length, zip: deflated( buf ) };
}

/**
 * Pick the glyph color for an icon drawn on its brand color. Brands show
 * white logos on mid-tone colors (YouTube red, Instagram pink), so black is
 * used only on very light colors (Snapchat yellow, Kick green).
 */
function contrastText( hex ) {
	const [ r, g, b ] = [ 0, 2, 4 ].map( ( i ) => {
		const c = parseInt( hex.slice( i, i + 2 ), 16 ) / 255;
		return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
	} );
	const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
	return lum > 0.55 ? '#000000' : '#FFFFFF';
}

async function main() {
	for ( const dir of [ OUT_PACKS, OUT_SOCIAL, OUT_LICENSES ] ) {
		fs.mkdirSync( dir, { recursive: true } );
	}

	const packsById = {};
	const index = [];
	const rows = [];
	let totalRaw = 0;
	let totalZip = 0;

	for ( const def of PACKS ) {
		const t = Date.now();
		const { pack, raw, zip, skipped } = await buildPack( def );
		packsById[ def.id ] = pack;
		index.push( {
			id: pack.id,
			label: pack.label,
			style: def.style,
			license: pack.license,
			url: def.url,
			version: pack.version,
			count: pack.count,
			categories: pack.categories,
		} );
		totalRaw += raw;
		totalZip += zip;
		rows.push(
			`${ def.id.padEnd( 10 ) } ${ String( pack.count ).padStart( 6 ) } icons  ${ fmt( raw ) } raw  ${ fmt( zip ) } zipped` +
				( skipped ? `  (${ skipped } skipped)` : '' ) +
				`  ${ ( ( Date.now() - t ) / 1000 ).toFixed( 1 ) }s`
		);
	}

	const indexBuf = writeJson( path.join( OUT_PACKS, 'index.json' ), { packs: index } );
	const social = buildSocial( packsById );
	totalRaw += indexBuf.length + social.raw;
	totalZip += deflated( indexBuf ) + social.zip;

	const totalIcons = index.reduce( ( n, p ) => n + p.count, 0 );
	console.log( '\nIconjet – icon data\n' );
	console.log( rows.join( '\n' ) );
	console.log( `social     ${ String( social.count ).padStart( 6 ) } platforms ${ fmt( social.raw ) } raw  ${ fmt( social.zip ) } zipped` );
	console.log( '-'.repeat( 70 ) );
	console.log( `total      ${ String( totalIcons ).padStart( 6 ) } icons  ${ fmt( totalRaw ) } raw  ${ fmt( totalZip ) } zipped (icon data only)\n` );
}

main().catch( ( err ) => {
	console.error( err );
	process.exit( 1 );
} );
