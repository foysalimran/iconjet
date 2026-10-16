/**
 * Draws Iconjet icons inside the Elementor editor.
 *
 * Elementor's Icon Library modal, the icon control and the live preview
 * render font-style placeholders: <i class="iconjet-e iconjet-e--{pack}
 * iconjet-e-{pack}--{name}"></i>. This script fills each one with the SVG
 * from the plugin's own pack files (no icon font). It is loaded only in the
 * Elementor editor and its preview iframe (see Integrations\Elementor).
 */
const CONFIG = window.iconjetElementor || { packs: {} };
const SELECTOR = 'i.iconjet-e';
const CLASS_RE = /^iconjet-e-([a-z0-9-]+?)--([a-z0-9_-]+)$/;
const ATTRS = [
	'fill',
	'stroke',
	'stroke-width',
	'stroke-linecap',
	'stroke-linejoin',
	'fill-rule',
	'clip-rule',
];

const packs = new Map();

function loadPack( id ) {
	if ( ! packs.has( id ) ) {
		let request;
		if ( id === 'custom' ) {
			request = window.fetch( CONFIG.custom, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': CONFIG.nonce },
			} );
		} else if ( CONFIG.packs[ id ] ) {
			request = window.fetch( CONFIG.packs[ id ] );
		} else {
			request = Promise.reject( new Error( 'Unknown pack' ) );
		}
		packs.set(
			id,
			request
				.then( ( response ) => {
					if ( ! response.ok ) {
						throw new Error( `HTTP ${ response.status }` );
					}
					return response.json();
				} )
				.catch( () => {
					packs.delete( id );
					return null;
				} )
		);
	}
	return packs.get( id );
}

function iconRef( el ) {
	for ( const name of el.classList ) {
		const match = CLASS_RE.exec( name );
		if ( match ) {
			return { pack: match[ 1 ], name: match[ 2 ], key: name };
		}
	}
	return null;
}

function svgMarkup( pack, icon ) {
	const attrs = { ...( pack.attrs || {} ), ...( icon.a || {} ) };
	const doc = document.implementation.createHTMLDocument( '' );
	const svg = doc.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
	svg.setAttribute( 'viewBox', icon.v );
	svg.setAttribute( 'width', '1em' );
	svg.setAttribute( 'height', '1em' );
	svg.setAttribute( 'aria-hidden', 'true' );
	svg.setAttribute( 'focusable', 'false' );
	svg.setAttribute( 'class', 'iconjet-icon iconjet-elementor' );
	ATTRS.forEach( ( name ) => {
		if ( attrs[ name ] ) {
			svg.setAttribute( name, attrs[ name ] );
		}
	} );
	if ( ! attrs.fill ) {
		svg.setAttribute( 'fill', 'currentColor' );
	}
	// Pack data is the plugin's own files or custom icons sanitized on upload.
	svg.innerHTML = icon.b;
	return svg.outerHTML;
}

function hydrate( el ) {
	const ref = iconRef( el );
	if ( ! ref || el.dataset.iconjet === ref.key ) {
		return;
	}
	el.dataset.iconjet = ref.key;
	loadPack( ref.pack ).then( ( pack ) => {
		const icon = pack && pack.icons && pack.icons[ ref.name ];
		// The element may have been reused for another icon meanwhile.
		if ( icon && el.dataset.iconjet === ref.key ) {
			el.innerHTML = svgMarkup( pack, icon );
		}
	} );
}

function scan( root ) {
	if ( root.nodeType !== 1 ) {
		return;
	}
	if ( root.matches( SELECTOR ) ) {
		hydrate( root );
	}
	root.querySelectorAll( SELECTOR ).forEach( hydrate );
}

function start() {
	scan( document.body );
	let queued = new Set();
	let frame = 0;
	new window.MutationObserver( ( mutations ) => {
		mutations.forEach( ( mutation ) => {
			if ( mutation.type === 'attributes' ) {
				queued.add( mutation.target );
			} else {
				mutation.addedNodes.forEach( ( node ) => queued.add( node ) );
			}
		} );
		if ( ! frame ) {
			frame = window.requestAnimationFrame( () => {
				const nodes = queued;
				queued = new Set();
				frame = 0;
				nodes.forEach( scan );
			} );
		}
	} ).observe( document.body, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: [ 'class' ],
	} );
}

if ( document.body ) {
	start();
} else {
	document.addEventListener( 'DOMContentLoaded', start );
}
