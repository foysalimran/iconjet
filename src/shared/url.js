/**
 * Add https:// to a typed URL that has no scheme ("example.com/x").
 * Leaves mailto:, tel:, app schemes, anchors and relative paths unchanged.
 * Call it when the field loses focus, not on every keystroke: typing
 * "https://…" would otherwise become "https://h" after the first letter.
 *
 * @param {string} value Typed URL.
 * @return {string} URL.
 */
export function withScheme( value ) {
	const url = String( value || '' ).trim();
	if ( ! url || /^[a-z][a-z0-9+.-]*:/i.test( url ) || /^[#/?]/.test( url ) ) {
		return url;
	}
	return 'https://' + url.replace( /^\/\//, '' );
}
