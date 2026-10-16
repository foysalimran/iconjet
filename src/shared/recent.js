/**
 * Recently used icons, kept in localStorage.
 *
 * Storage can be unavailable (private windows, blocked site data), so every
 * access is wrapped in try/catch and failures simply mean "no recent icons".
 */
const KEY = 'iconjet-recent';
const MAX = 24;

export function getRecent() {
	try {
		const list = JSON.parse( window.localStorage.getItem( KEY ) || '[]' );
		return Array.isArray( list )
			? list.filter( ( i ) => i && i.ref && i.v && i.b )
			: [];
	} catch {
		return [];
	}
}

export function addRecent( icon ) {
	try {
		const entry = {
			ref: icon.ref,
			name: icon.name,
			v: icon.v,
			b: icon.b,
			a: icon.a,
		};
		const list = [
			entry,
			...getRecent().filter( ( i ) => i.ref !== icon.ref ),
		].slice( 0, MAX );
		window.localStorage.setItem( KEY, JSON.stringify( list ) );
	} catch {
		// Ignore: recent icons are a convenience only.
	}
}
