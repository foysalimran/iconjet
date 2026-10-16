import { useBlockProps } from '@wordpress/block-editor';

import IconSvg from '../../shared/svg';
import {
	glyphSize,
	itemIcon,
	linkRel,
	linkStyle,
	linkTarget,
	listClassName,
	listStyle,
} from './shared';

/**
 * Static save: each item stores its SVG and brand colors, so the icons keep
 * rendering if Iconjet is deactivated. Items without a URL are not output.
 * See ../icon/deprecated.js for the deprecation strategy.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {Element|null} Saved markup.
 */
export default function save( props ) {
	const { attributes } = props;
	const items = attributes.items.filter(
		( item ) => item.url && item.v && item.b
	);
	if ( ! items.length ) {
		return null;
	}

	const blockProps = useBlockProps.save( {
		className: listClassName( attributes ),
		style: listStyle( attributes ),
	} );

	return (
		<ul { ...blockProps }>
			{ items.map( ( item, index ) => (
				<li
					key={ index }
					className="iconjet-social__item"
					style={ { margin: 0 } }
				>
					<a
						className={ `iconjet-social__link iconjet-social__link--${ item.id }` }
						href={ item.url }
						target={ linkTarget(
							item.url,
							attributes.openInNewTab
						) }
						rel={ linkRel( item.url, attributes ) }
						aria-label={ item.label }
						style={ linkStyle( item, attributes ) }
					>
						<IconSvg
							icon={ itemIcon( item ) }
							size={ glyphSize( attributes ) }
						/>
					</a>
				</li>
			) ) }
		</ul>
	);
}
