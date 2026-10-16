/**
 * Data loading for pickers.
 *
 * Pack files are fetched lazily from the plugin's own assets/packs/ folder
 * (same origin, no CDN) and kept in memory for the editor session.
 */
import apiFetch from '@wordpress/api-fetch';

let packsRequest = null;
let platformsRequest = null;
const packCache = new Map();

/**
 * Enabled packs, editor defaults and data URLs.
 *
 * @return {Promise<{packs: Array, defaults: Object, social_url: string}>} Response.
 */
export function getPacks() {
	if ( ! packsRequest ) {
		packsRequest = apiFetch( { path: '/iconjet/v1/packs' } ).catch(
			( error ) => {
				packsRequest = null;
				throw error;
			}
		);
	}
	return packsRequest;
}

/**
 * Social platforms with their embedded SVG.
 *
 * @return {Promise<Array>} Platforms.
 */
export function getPlatforms() {
	if ( ! platformsRequest ) {
		platformsRequest = getPacks()
			.then( ( data ) => fetchJson( data.social_url ) )
			.then( ( data ) => data.platforms )
			.catch( ( error ) => {
				platformsRequest = null;
				throw error;
			} );
	}
	return platformsRequest;
}

/**
 * Load a pack as a flat, searchable list.
 *
 * @param {Object} pack Pack descriptor from getPacks().
 * @return {Promise<Array<{name:string, ref:string, v:string, b:string, a:Object, c:Array, hay:string}>>} Icons.
 */
export function loadPack( pack ) {
	if ( ! packCache.has( pack.id ) ) {
		const request =
			pack.id === 'custom'
				? apiFetch( { path: '/iconjet/v1/custom-icons' } )
				: fetchJson( pack.url );
		packCache.set(
			pack.id,
			request.then( normalizePack ).catch( ( error ) => {
				packCache.delete( pack.id );
				throw error;
			} )
		);
	}
	return packCache.get( pack.id );
}

/**
 * Forget cached data, e.g. after custom icons change.
 *
 * @param {string} [packId] Pack id; omit to clear everything.
 */
export function clearCache( packId ) {
	if ( packId ) {
		packCache.delete( packId );
	} else {
		packCache.clear();
	}
	packsRequest = null;
}

function fetchJson( url ) {
	return window
		.fetch( url, { credentials: 'same-origin' } )
		.then( ( response ) => {
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			return response.json();
		} );
}

function normalizePack( data ) {
	const attrs = data.attrs || {};
	return Object.keys( data.icons || {} ).map( ( name ) => {
		const icon = data.icons[ name ];
		return {
			name,
			ref: `${ data.id }:${ name }`,
			v: icon.v,
			b: icon.b,
			a: { ...attrs, ...( icon.a || {} ) },
			c: icon.c || [],
			label: icon.l,
			hay: [ name.replace( /[-_]/g, ' ' ), ...( icon.t || [] ) ]
				.join( ' ' )
				.toLowerCase(),
		};
	} );
}
