/**
 * Helpers shared by edit and save so both produce the same markup.
 *
 * Color, padding and border radius use the native block supports (so the
 * standard panels and theme presets work) with __experimentalSkipSerialization:
 * WordPress stores the values in the usual attributes but does not write them
 * onto the wrapper. This file turns those attributes into classes and inline
 * styles on the inner icon box. The wrapper stays full width, so theme layout
 * rules (constrained layouts center fit-content children) cannot move the
 * icon; alignment is a flex justify on the wrapper.
 *
 * Output depends only on block attributes and this code, never on
 * experimental editor helpers, so saved markup stays valid across WordPress
 * versions.
 */
export const DEFAULT_SIZE = 48;

/**
 * Wrapper style: full-width flex row, aligned with justify-content.
 *
 * @param {string} justification left | center | right | undefined (start).
 * @return {Object} Style.
 */
export function wrapperStyle( justification ) {
	return {
		display: 'flex',
		justifyContent: justification || undefined,
	};
}

/**
 * "var:preset|spacing|40" -> "var(--wp--preset--spacing--40)".
 *
 * @param {string} value Style value.
 * @return {string|undefined} CSS value.
 */
export function cssValue( value ) {
	if ( typeof value !== 'string' || value === '' ) {
		return undefined;
	}
	const preset = value.match( /^var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)$/i );
	return preset
		? `var(--wp--preset--${ preset[ 1 ] }--${ preset[ 2 ] })`
		: value;
}

function sides( value, property, style ) {
	if ( typeof value === 'string' ) {
		style[ property ] = cssValue( value );
		return;
	}
	if ( value && typeof value === 'object' ) {
		[ 'Top', 'Right', 'Bottom', 'Left' ].forEach( ( side ) => {
			const v = cssValue( value[ side.toLowerCase() ] );
			if ( v ) {
				style[ property + side ] = v;
			}
		} );
	}
}

function radius( value, style ) {
	if ( typeof value === 'string' ) {
		style.borderRadius = cssValue( value );
		return;
	}
	if ( value && typeof value === 'object' ) {
		[ 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' ].forEach(
			( corner ) => {
				const v = cssValue( value[ corner ] );
				if ( v ) {
					style[
						'border' +
							corner[ 0 ].toUpperCase() +
							corner.slice( 1 ) +
							'Radius'
					] = v;
				}
			}
		);
	}
}

/**
 * Classes and inline style for the icon box (the element around the SVG).
 *
 * @param {Object} attributes Block attributes.
 * @return {{className: string, style: Object}} Props.
 */
export function boxProps( attributes ) {
	const { textColor, backgroundColor, style: custom = {} } = attributes;
	const color = custom.color || {};
	const classes = [ 'iconjet-icon__box' ];
	const style = {
		display: 'inline-flex',
		lineHeight: 0,
	};

	if ( textColor || color.text ) {
		classes.push( 'has-text-color' );
	}
	if ( textColor ) {
		classes.push( `has-${ textColor }-color` );
	} else if ( color.text ) {
		style.color = cssValue( color.text );
	}

	if ( backgroundColor || color.background ) {
		classes.push( 'has-background' );
	}
	if ( backgroundColor ) {
		classes.push( `has-${ backgroundColor }-background-color` );
	} else if ( color.background ) {
		style.backgroundColor = cssValue( color.background );
	}

	sides( custom.spacing && custom.spacing.padding, 'padding', style );
	radius( custom.border && custom.border.radius, style );

	return { className: classes.join( ' ' ), style };
}

/**
 * Accessible name for a linked icon: the label, or the icon name.
 *
 * @param {string} label Label attribute.
 * @param {string} icon  Icon reference.
 * @return {string} Name.
 */
export function linkLabel( label, icon ) {
	if ( label ) {
		return label;
	}
	const name = ( icon || '' ).split( ':' )[ 1 ] || '';
	return name
		.replace( /^(solid|regular|brands)-/, '' )
		.replace( /[-_]+/g, ' ' );
}
