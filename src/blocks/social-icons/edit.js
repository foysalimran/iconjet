import { __, sprintf } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	JustifyContentControl,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';

import IconSvg from '../../shared/svg';
import { getPacks, getPlatforms } from '../../shared/api';
import { withScheme } from '../../shared/url';
import {
	DEFAULT_STYLE,
	glyphSize,
	itemIcon,
	linkStyle,
	listClassName,
	listStyle,
} from './shared';
import blockIcon from './block-icon';

const STYLE_OPTIONS = [
	{ value: 'circle', label: __( 'Circle', 'iconjet' ) },
	{ value: 'rounded', label: __( 'Rounded', 'iconjet' ) },
	{ value: 'square', label: __( 'Square', 'iconjet' ) },
	{ value: 'icon', label: __( 'Icon only', 'iconjet' ) },
];

/**
 * Keep only the platform fields the block saves.
 *
 * @param {Object} platform Platform from platforms.json.
 * @return {Object} Item.
 */
function toItem( platform ) {
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

export default function Edit( { attributes, setAttributes } ) {
	const {
		items,
		iconStyle,
		size,
		gap,
		brandColors,
		iconColor,
		hoverColor,
		openInNewTab,
		nofollow,
		justification,
	} = attributes;
	const [ platforms, setPlatforms ] = useState( null );
	const [ defaultStyle, setDefaultStyle ] = useState( DEFAULT_STYLE );
	const [ loadError, setLoadError ] = useState( false );

	useEffect( () => {
		getPlatforms()
			.then( setPlatforms )
			.catch( () => setLoadError( true ) );
		getPacks()
			.then( ( data ) => setDefaultStyle( data.defaults.social_style ) )
			.catch( () => {} );
	}, [] );

	const blockProps = useBlockProps( {
		className: listClassName( attributes ),
		style: listStyle( attributes ),
	} );

	const options = useMemo(
		() =>
			( platforms || [] )
				.filter( ( p ) => ! items.some( ( item ) => item.id === p.id ) )
				.map( ( p ) => ( { value: p.id, label: p.label } ) ),
		[ platforms, items ]
	);

	const addItem = ( id ) => {
		const platform = id && platforms.find( ( p ) => p.id === id );
		if ( ! platform ) {
			return;
		}
		const next = { items: [ ...items, toItem( platform ) ] };
		if ( ! iconStyle ) {
			next.iconStyle = defaultStyle;
		}
		setAttributes( next );
	};

	const updateItem = ( index, changes ) =>
		setAttributes( {
			items: items.map( ( item, i ) =>
				i === index ? { ...item, ...changes } : item
			),
		} );

	const moveItem = ( index, offset ) => {
		const next = [ ...items ];
		const [ moved ] = next.splice( index, 1 );
		next.splice( index + offset, 0, moved );
		setAttributes( { items: next } );
	};

	const removeItem = ( index ) =>
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );

	const addControl = platforms ? (
		<ComboboxControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Add a platform', 'iconjet' ) }
			value={ null }
			options={ options }
			onChange={ addItem }
		/>
	) : (
		! loadError && <Spinner />
	);

	if ( ! items.length ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ blockIcon }
					label={ __( 'Iconjet Social Icons', 'iconjet' ) }
					instructions={ __(
						'Add the platforms you want to link to.',
						'iconjet'
					) }
				>
					{ loadError
						? __(
								'The social platforms could not be loaded.',
								'iconjet'
							)
						: addControl }
				</Placeholder>
			</div>
		);
	}

	const shape = iconStyle || DEFAULT_STYLE;

	return (
		<>
			<BlockControls group="block">
				<JustifyContentControl
					allowedControls={ [ 'left', 'center', 'right' ] }
					value={ justification }
					onChange={ ( value ) =>
						setAttributes( { justification: value } )
					}
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Profiles', 'iconjet' ) }>
					{ items.map( ( item, index ) => (
						<div
							className="iconjet-social-editor__item"
							key={ item.id }
						>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								type="url"
								label={ item.label }
								placeholder={
									( platforms &&
										(
											platforms.find(
												( p ) => p.id === item.id
											) || {}
										).placeholder ) ||
									''
								}
								value={ item.url }
								onChange={ ( value ) =>
									updateItem( index, {
										url: withScheme( value ),
									} )
								}
							/>
							<Button
								icon="arrow-up-alt2"
								size="small"
								label={ sprintf(
									/* translators: %s: platform name. */ __(
										'Move %s up',
										'iconjet'
									),
									item.label
								) }
								disabled={ index === 0 }
								onClick={ () => moveItem( index, -1 ) }
							/>
							<Button
								icon="arrow-down-alt2"
								size="small"
								label={ sprintf(
									/* translators: %s: platform name. */ __(
										'Move %s down',
										'iconjet'
									),
									item.label
								) }
								disabled={ index === items.length - 1 }
								onClick={ () => moveItem( index, 1 ) }
							/>
							<Button
								icon="no-alt"
								size="small"
								isDestructive
								label={ sprintf(
									/* translators: %s: platform name. */ __(
										'Remove %s',
										'iconjet'
									),
									item.label
								) }
								onClick={ () => removeItem( index ) }
							/>
						</div>
					) ) }
					{ addControl }
				</PanelBody>

				<PanelBody title={ __( 'Settings', 'iconjet' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Style', 'iconjet' ) }
						value={ shape }
						options={ STYLE_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { iconStyle: value } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Size (px)', 'iconjet' ) }
						min={ 16 }
						max={ 128 }
						value={ size }
						onChange={ ( value ) =>
							setAttributes( { size: value || 28 } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Spacing (px)', 'iconjet' ) }
						min={ 0 }
						max={ 64 }
						value={ gap }
						onChange={ ( value ) =>
							setAttributes( { gap: value ?? 8 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Open links in a new tab', 'iconjet' ) }
						checked={ openInNewTab }
						onChange={ ( value ) =>
							setAttributes( { openInNewTab: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Add rel="nofollow"', 'iconjet' ) }
						checked={ nofollow }
						onChange={ ( value ) =>
							setAttributes( { nofollow: value } )
						}
					/>
				</PanelBody>

				<PanelBody title={ __( 'Colors', 'iconjet' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Use brand colors', 'iconjet' ) }
						checked={ brandColors }
						onChange={ ( value ) =>
							setAttributes( { brandColors: value } )
						}
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Custom colors', 'iconjet' ) }
					initialOpen={ ! brandColors || !! hoverColor }
					colorSettings={ [
						...( brandColors
							? []
							: [
									{
										value: iconColor,
										onChange: ( value ) =>
											setAttributes( {
												iconColor: value || '',
											} ),
										label:
											shape === 'icon'
												? __( 'Icon color', 'iconjet' )
												: __(
														'Background color',
														'iconjet'
													),
									},
								] ),
						{
							value: hoverColor,
							onChange: ( value ) =>
								setAttributes( { hoverColor: value || '' } ),
							label: __( 'Hover color', 'iconjet' ),
						},
					] }
				/>
			</InspectorControls>

			<ul { ...blockProps }>
				{ items.map( ( item ) => (
					<li
						key={ item.id }
						className={ `iconjet-social__item${ item.url ? '' : ' is-empty' }` }
						style={ { margin: 0 } }
					>
						<span
							className={ `iconjet-social__link iconjet-social__link--${ item.id }` }
							style={ linkStyle( item, attributes ) }
							title={
								item.url
									? item.label
									: sprintf(
											/* translators: %s: platform name. */ __(
												'%s: add a URL in the block settings',
												'iconjet'
											),
											item.label
										)
							}
						>
							<IconSvg
								icon={ itemIcon( item ) }
								size={ glyphSize( attributes ) }
								label={ item.label }
							/>
						</span>
					</li>
				) ) }
			</ul>
		</>
	);
}
