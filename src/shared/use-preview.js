/**
 * Server-rendered shortcode preview.
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';

/**
 * Wait after the last change before asking for a preview, so dragging a
 * slider or typing sends one request instead of one per change.
 */
export const PREVIEW_DELAY = 300;

/**
 * Preview request key for useShortcodePreview().
 *
 * A string, so the preview effect only runs when the request really changes
 * (objects and arrays are new on every render).
 *
 * @param {string} type 'icon' or 'social'.
 * @param {Object} atts Shortcode attributes; empty values are left out.
 * @return {string} Request key.
 */
export function previewKey( type, atts ) {
	const data = {};
	Object.keys( atts ).forEach( ( key ) => {
		if ( atts[ key ] !== '' && atts[ key ] !== undefined ) {
			data[ key ] = String( atts[ key ] );
		}
	} );
	return JSON.stringify( { type, atts: data } );
}

/**
 * Preview HTML from the server's shortcode callbacks (the same renderer as
 * the frontend).
 *
 * - Requests only when `request` changes, after PREVIEW_DELAY ms.
 * - A newer request cancels the older one, so a slow response can never
 *   replace a newer preview.
 * - The previous preview stays visible while the next one loads.
 *
 * @param {string} request Key from previewKey(), or '' for no preview.
 * @return {{html: string, loading: boolean}} Preview state.
 */
export default function useShortcodePreview( request ) {
	const [ html, setHtml ] = useState( '' );
	const [ loading, setLoading ] = useState( false );

	useEffect( () => {
		if ( ! request ) {
			setHtml( '' );
			setLoading( false );
			return undefined;
		}

		let current = true;
		const controller =
			typeof window.AbortController === 'function'
				? new window.AbortController()
				: null;
		setLoading( true );

		const timer = setTimeout( () => {
			apiFetch( {
				path: '/iconjet/v1/preview',
				method: 'POST',
				data: JSON.parse( request ),
				signal: controller ? controller.signal : undefined,
			} )
				.then( ( response ) => current && setHtml( response.html ) )
				.catch( () => current && setHtml( '' ) )
				.finally( () => current && setLoading( false ) );
		}, PREVIEW_DELAY );

		return () => {
			current = false;
			clearTimeout( timer );
			if ( controller ) {
				controller.abort();
			}
		};
	}, [ request ] );

	return { html, loading };
}
