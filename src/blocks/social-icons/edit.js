import { __, sprintf } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	JustifyContentControl,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

import IconSvg from '../../shared/svg';
import { getPacks, getPlatforms } from '../../shared/api';
import SocialProfiles from '../../shared/social-profiles';
import { STYLE_OPTIONS } from '../../shared/social-styles';
import {
	DEFAULT_STYLE,
	glyphSize,
	itemIcon,
	linkStyle,
	listClassName,
	listStyle,
} from './shared';
import blockIcon from './block-icon';

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

	const onChange = ( next ) => setAttributes( { items: next } );
	// The first platform added also fixes the style to the site default.
	const onAdd = ( next ) =>
		setAttributes(
			iconStyle
				? { items: next }
				: { items: next, iconStyle: defaultStyle }
		);

	let profiles = null;
	if ( platforms ) {
		profiles = (
			<SocialProfiles
				items={ items }
				platforms={ platforms }
				onChange={ onChange }
				onAdd={ onAdd }
			/>
		);
	} else if ( loadError ) {
		profiles = __( 'The social platforms could not be loaded.', 'iconjet' );
	} else {
		profiles = <Spinner />;
	}

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
					{ profiles }
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
					{ profiles }
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
						className={ `iconjet-social__item${
							item.url ? '' : ' is-empty'
						}` }
						style={ { margin: 0 } }
					>
						<span
							className={ `iconjet-social__link iconjet-social__link--${ item.id }` }
							style={ linkStyle( item, attributes ) }
							title={
								item.url
									? item.label
									: sprintf(
											/* translators: %s: platform name. */
											__(
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
