import { useBlockProps } from '@wordpress/block-editor';

import IconSvg, { getTransform } from '../../shared/svg';
import { DEFAULT_SIZE, boxProps, linkLabel, wrapperStyle } from './shared';

/**
 * Static save: the complete SVG is stored in post content, so the icon keeps
 * rendering if Iconjet is deactivated. See deprecated.js before changing.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {Element|null} Saved markup.
 */
export default function save( props ) {
	const { attributes } = props;
	const {
		icon,
		svg,
		size,
		label,
		rotation,
		flipHorizontal,
		flipVertical,
		url,
		linkTarget,
		rel,
		justification,
	} = attributes;

	if ( ! svg ) {
		return null;
	}

	const [ pack ] = icon.split( ':' );
	const box = boxProps( attributes );
	const graphic = (
		<IconSvg
			icon={ svg }
			size={ size || DEFAULT_SIZE }
			label={ url ? '' : label }
			className={ `iconjet-icon iconjet-${ pack }` }
			transform={ getTransform(
				svg.v,
				rotation,
				flipHorizontal,
				flipVertical
			) }
		/>
	);

	return (
		<div
			{ ...useBlockProps.save( {
				style: wrapperStyle( justification ),
			} ) }
		>
			{ url ? (
				<a
					className={ `${ box.className } iconjet-icon__link` }
					style={ box.style }
					href={ url }
					target={ linkTarget || undefined }
					rel={ rel || undefined }
					aria-label={ linkLabel( label, icon ) }
				>
					{ graphic }
				</a>
			) : (
				<span className={ box.className } style={ box.style }>
					{ graphic }
				</span>
			) }
		</div>
	);
}
