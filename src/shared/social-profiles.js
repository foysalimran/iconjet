/**
 * Editable list of social profiles: URL per platform, reorder, remove, add.
 * Used by the Social Icons block and the Shortcode Generator.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, ComboboxControl, TextControl } from '@wordpress/components';
import { useMemo } from '@wordpress/element';

import { withScheme } from './url';

/**
 * Platform fields stored for an item (the block saves them in content).
 *
 * @param {Object} platform Platform from platforms.json.
 * @return {Object} Item.
 */
export function toItem( platform ) {
	const item = {
		id: platform.id,
		url: '',
		label: platform.label,
		color: platform.color,
		fg: platform.fg,
		v: platform.v,
		b: platform.b,
	};
	if ( platform.a ) {
		item.a = platform.a;
	}
	return item;
}

/**
 * @param {Object}   props
 * @param {Array}    props.items     Items.
 * @param {Array}    props.platforms All platforms.
 * @param {Function} props.onChange  ( items ) => void
 * @param {Function} props.onAdd     Optional ( items ) => void for the first add.
 */
export default function SocialProfiles( {
	items,
	platforms,
	onChange,
	onAdd,
} ) {
	const options = useMemo(
		() =>
			platforms
				.filter( ( p ) => ! items.some( ( item ) => item.id === p.id ) )
				.map( ( p ) => ( { value: p.id, label: p.label } ) ),
		[ platforms, items ]
	);

	const update = ( index, changes ) =>
		onChange(
			items.map( ( item, i ) =>
				i === index ? { ...item, ...changes } : item
			)
		);

	const move = ( index, offset ) => {
		const next = [ ...items ];
		const [ moved ] = next.splice( index, 1 );
		next.splice( index + offset, 0, moved );
		onChange( next );
	};

	const add = ( id ) => {
		const platform = id && platforms.find( ( p ) => p.id === id );
		if ( platform ) {
			( onAdd || onChange )( [ ...items, toItem( platform ) ] );
		}
	};

	return (
		<div className="iconjet-profiles">
			{ items.map( ( item, index ) => {
				const platform = platforms.find( ( p ) => p.id === item.id );
				return (
					<div className="iconjet-profiles__item" key={ item.id }>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="url"
							label={ item.label }
							placeholder={ platform ? platform.placeholder : '' }
							value={ item.url }
							onChange={ ( value ) =>
								update( index, { url: value } )
							}
							onBlur={ () =>
								update( index, { url: withScheme( item.url ) } )
							}
						/>
						<Button
							icon="arrow-up-alt2"
							size="small"
							label={ sprintf(
								/* translators: %s: platform name. */
								__( 'Move %s up', 'iconjet' ),
								item.label
							) }
							disabled={ index === 0 }
							accessibleWhenDisabled
							onClick={ () => move( index, -1 ) }
						/>
						<Button
							icon="arrow-down-alt2"
							size="small"
							label={ sprintf(
								/* translators: %s: platform name. */
								__( 'Move %s down', 'iconjet' ),
								item.label
							) }
							disabled={ index === items.length - 1 }
							accessibleWhenDisabled
							onClick={ () => move( index, 1 ) }
						/>
						<Button
							icon="no-alt"
							size="small"
							isDestructive
							label={ sprintf(
								/* translators: %s: platform name. */
								__( 'Remove %s', 'iconjet' ),
								item.label
							) }
							onClick={ () =>
								onChange(
									items.filter( ( _, i ) => i !== index )
								)
							}
						/>
					</div>
				);
			} ) }
			<ComboboxControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Add a platform', 'iconjet' ) }
				value={ null }
				options={ options }
				onChange={ add }
			/>
		</div>
	);
}
