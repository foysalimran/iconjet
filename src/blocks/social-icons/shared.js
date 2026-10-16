/**
 * Markup helpers shared by edit and save (and mirrored by the PHP shortcode
 * renderer), so the editor preview matches the saved markup exactly.
 *
 * All visual styles needed to read the icons are inline, so saved content
 * keeps its shape and colors without the plugin stylesheet. The stylesheet
 * only adds hover and focus states.
 */
export const DEFAULT_STYLE = 'circle';

const RADIUS = {
	circle: '50%',
	rounded: '22%',
	square: '0',
};

const FALLBACK_BACKGROUND = '#1e1e1e';
const FALLBACK_GLYPH = '#ffffff';

export function listStyle( { gap, justification } ) {
	return {
		display: 'flex',
		flexWrap: 'wrap',
		alignItems: 'center',
		gap: `${ gap }px`,
		justifyContent: justification || undefined,
		// list-style-type, not list-style: kses drops list-style for users
		// without unfiltered_html, which would invalidate their blocks.
		listStyleType: 'none',
		padding: 0,
	};
}

export function listClassName( { iconStyle, hoverColor } ) {
	return [
		'iconjet-social',
		`iconjet-social--${ iconStyle || DEFAULT_STYLE }`,
		hoverColor && 'has-hover-color',
	]
		.filter( Boolean )
		.join( ' ' );
}

/**
 * Inline style for one link.
 *
 * - Icon only: the glyph uses the brand color (or the custom color).
 * - Circle / square / rounded: the background uses the brand color (or the
 *   custom color) and the glyph is white, or black on very light brands.
 *
 * @param {Object}  item                   Platform item (color, fg).
 * @param {Object}  attributes             Block attributes.
 * @param {string}  attributes.iconStyle   circle | rounded | square | icon.
 * @param {number}  attributes.size        Link size in px.
 * @param {boolean} attributes.brandColors Use brand colors.
 * @param {string}  attributes.iconColor   Custom color.
 * @param {string}  attributes.hoverColor  Hover color.
 * @return {Object} Style.
 */
export function linkStyle(
	item,
	{ iconStyle, size, brandColors, iconColor, hoverColor }
) {
	const shape = iconStyle || DEFAULT_STYLE;
	const style = {
		display: 'inline-flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: `${ size }px`,
		height: `${ size }px`,
	};
	if ( shape === 'icon' ) {
		style.color = brandColors ? item.color : iconColor || undefined;
	} else {
		style.borderRadius = RADIUS[ shape ];
		style.backgroundColor = brandColors
			? item.color
			: iconColor || FALLBACK_BACKGROUND;
		style.color = brandColors ? item.fg : FALLBACK_GLYPH;
	}
	if ( hoverColor ) {
		style[ '--iconjet-hover' ] = hoverColor;
	}
	return style;
}

export function glyphSize( { iconStyle, size } ) {
	return ( iconStyle || DEFAULT_STYLE ) === 'icon'
		? size
		: Math.round( size * 0.55 );
}

export function linkTarget( url, openInNewTab ) {
	return openInNewTab && ! /^(mailto|tel|sms):/i.test( url )
		? '_blank'
		: undefined;
}

export function linkRel( url, { openInNewTab, nofollow } ) {
	const rel = [];
	if ( linkTarget( url, openInNewTab ) ) {
		rel.push( 'noopener' );
	}
	if ( nofollow ) {
		rel.push( 'nofollow' );
	}
	return rel.length ? rel.join( ' ' ) : undefined;
}

export function itemIcon( item ) {
	return { v: item.v, b: item.b, a: item.a || { fill: 'currentColor' } };
}
