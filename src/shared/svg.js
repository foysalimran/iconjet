/**
 * Inline SVG element used by the editor and by block save().
 *
 * Icon data is { v: viewBox, b: inner markup, a: root attributes }.
 * Inner markup comes from the plugin's own pack files or from custom icons
 * that were sanitized on the server, so it is set with dangerouslySetInnerHTML.
 */

// Root SVG attributes as React props. The @wordpress/element serializer
// turns these back into their dashed names in saved markup.
const REACT_ATTRS = {
	'stroke-width': 'strokeWidth',
	'stroke-linecap': 'strokeLinecap',
	'stroke-linejoin': 'strokeLinejoin',
	'fill-rule': 'fillRule',
	'clip-rule': 'clipRule',
};

function rootProps( attrs = {} ) {
	const props = {};
	Object.keys( attrs ).forEach( ( name ) => {
		props[ REACT_ATTRS[ name ] || name ] = attrs[ name ];
	} );
	return props;
}

/**
 * SVG transform for rotation and flips, around the viewBox center.
 *
 * Applied to an inner <g>, so it is plain SVG markup: it keeps working when
 * the plugin is inactive and passes the post content kses filters.
 *
 * @param {string}  viewBox        viewBox value.
 * @param {number}  rotation       Degrees, 0–360.
 * @param {boolean} flipHorizontal Mirror left-right.
 * @param {boolean} flipVertical   Mirror top-bottom.
 * @return {string} Transform, or '' when nothing is applied.
 */
export function getTransform(
	viewBox,
	rotation,
	flipHorizontal,
	flipVertical
) {
	const [ x = 0, y = 0, w = 24, h = 24 ] = String( viewBox )
		.trim()
		.split( /[\s,]+/ )
		.map( Number );
	const cx = round( x + w / 2 );
	const cy = round( y + h / 2 );
	const parts = [];
	const deg = Number( rotation ) % 360;
	if ( deg ) {
		parts.push( `rotate(${ deg } ${ cx } ${ cy })` );
	}
	if ( flipHorizontal || flipVertical ) {
		const sx = flipHorizontal ? -1 : 1;
		const sy = flipVertical ? -1 : 1;
		parts.push(
			`translate(${ flipHorizontal ? round( 2 * cx ) : 0 } ${
				flipVertical ? round( 2 * cy ) : 0
			}) scale(${ sx } ${ sy })`
		);
	}
	return parts.join( ' ' );
}

function round( n ) {
	return Math.round( n * 1000 ) / 1000;
}

/**
 * @param {Object} props
 * @param {Object} props.icon      Icon data { v, b, a }.
 * @param {number} props.size      Width and height in px.
 * @param {string} props.label     Accessible name; empty = decorative.
 * @param {string} props.className Extra classes.
 * @param {string} props.transform SVG transform for the content.
 * @param {Object} props.style     Inline style.
 */
export default function IconSvg( {
	icon,
	size,
	label,
	className,
	transform,
	style,
} ) {
	if ( ! icon || ! icon.v || ! icon.b ) {
		return null;
	}
	const body = transform
		? `<g transform="${ transform }">${ icon.b }</g>`
		: icon.b;
	const a11y = label
		? { role: 'img', 'aria-label': label }
		: { 'aria-hidden': 'true', focusable: 'false' };

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox={ icon.v }
			width={ size }
			height={ size }
			{ ...rootProps( icon.a ) }
			className={ className }
			style={ style }
			{ ...a11y }
			dangerouslySetInnerHTML={ { __html: body } }
		/>
	);
}
