import { __ } from '@wordpress/i18n';
import {
	AlignmentControl,
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	// ToolsPanel is the panel core blocks use for their settings. It is
	// still exported with the __experimental prefix in @wordpress/components.
	// It only affects the editor UI, never saved markup.
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToolsPanel as ToolsPanel,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

import IconSvg, { getTransform } from '../../shared/svg';
import IconPickerModal from '../../shared/icon-picker';
import { getPacks } from '../../shared/api';
import { withScheme } from '../../shared/url';
import { DEFAULT_SIZE, boxProps, linkLabel, wrapperStyle } from './shared';
import blockIcon from './block-icon';

const NEW_TAB_REL = 'noopener';

export default function Edit( { attributes, setAttributes } ) {
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
	const [ isPickerOpen, setPickerOpen ] = useState( false );
	const [ defaultSize, setDefaultSize ] = useState( DEFAULT_SIZE );
	const blockProps = useBlockProps( {
		style: wrapperStyle( justification ),
	} );

	useEffect( () => {
		getPacks()
			.then( ( data ) => setDefaultSize( data.defaults.icon_size ) )
			.catch( () => {} );
	}, [] );

	const onSelect = ( { ref, svg: data } ) => {
		const next = { icon: ref, svg: data };
		if ( ! size ) {
			next.size = defaultSize;
		}
		setAttributes( next );
		setPickerOpen( false );
	};

	const picker = isPickerOpen && (
		<IconPickerModal
			value={ icon }
			onSelect={ onSelect }
			onClose={ () => setPickerOpen( false ) }
		/>
	);

	if ( ! svg ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon={ blockIcon }
					label={ __( 'Iconjet Icon', 'iconjet' ) }
					instructions={ __(
						'Choose an icon from the library or from your uploaded icons.',
						'iconjet'
					) }
				>
					<Button
						variant="primary"
						onClick={ () => setPickerOpen( true ) }
					>
						{ __( 'Choose icon', 'iconjet' ) }
					</Button>
				</Placeholder>
				{ picker }
			</div>
		);
	}

	const resetAll = () =>
		setAttributes( {
			size: defaultSize,
			rotation: 0,
			flipHorizontal: false,
			flipVertical: false,
			label: '',
		} );

	const toggleNewTab = ( value ) => {
		const parts = rel
			.split( /\s+/ )
			.filter( ( part ) => part && part !== NEW_TAB_REL );
		if ( value ) {
			parts.push( NEW_TAB_REL );
		}
		setAttributes( {
			linkTarget: value ? '_blank' : '',
			rel: parts.join( ' ' ),
		} );
	};

	return (
		<>
			<BlockControls group="block">
				<AlignmentControl
					value={ justification }
					onChange={ ( value ) =>
						setAttributes( { justification: value } )
					}
				/>
				<ToolbarButton onClick={ () => setPickerOpen( true ) }>
					{ __( 'Replace icon', 'iconjet' ) }
				</ToolbarButton>
			</BlockControls>

			<InspectorControls>
				<ToolsPanel
					label={ __( 'Icon', 'iconjet' ) }
					resetAll={ resetAll }
				>
					<ToolsPanelItem
						label={ __( 'Size', 'iconjet' ) }
						hasValue={ () => !! size && size !== defaultSize }
						onDeselect={ () =>
							setAttributes( { size: defaultSize } )
						}
						isShownByDefault
					>
						<RangeControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Size (px)', 'iconjet' ) }
							min={ 8 }
							max={ 512 }
							value={ size || defaultSize }
							onChange={ ( value ) =>
								setAttributes( { size: value || defaultSize } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Rotation', 'iconjet' ) }
						hasValue={ () => !! rotation }
						onDeselect={ () => setAttributes( { rotation: 0 } ) }
						isShownByDefault
					>
						<RangeControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Rotation (degrees)', 'iconjet' ) }
							min={ 0 }
							max={ 360 }
							value={ rotation }
							onChange={ ( value ) =>
								setAttributes( { rotation: value || 0 } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Flip', 'iconjet' ) }
						hasValue={ () => flipHorizontal || flipVertical }
						onDeselect={ () =>
							setAttributes( {
								flipHorizontal: false,
								flipVertical: false,
							} )
						}
						isShownByDefault
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Flip horizontally', 'iconjet' ) }
							checked={ flipHorizontal }
							onChange={ ( value ) =>
								setAttributes( { flipHorizontal: value } )
							}
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Flip vertically', 'iconjet' ) }
							checked={ flipVertical }
							onChange={ ( value ) =>
								setAttributes( { flipVertical: value } )
							}
						/>
					</ToolsPanelItem>
					<ToolsPanelItem
						label={ __( 'Accessible label', 'iconjet' ) }
						hasValue={ () => !! label }
						onDeselect={ () => setAttributes( { label: '' } ) }
						isShownByDefault
					>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Accessible label', 'iconjet' ) }
							help={ __(
								'Describe what the icon means. Leave empty for decorative icons, which screen readers skip.',
								'iconjet'
							) }
							value={ label }
							onChange={ ( value ) =>
								setAttributes( { label: value } )
							}
						/>
					</ToolsPanelItem>
				</ToolsPanel>

				<PanelBody
					title={ __( 'Link', 'iconjet' ) }
					initialOpen={ !! url }
				>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="url"
						label={ __( 'URL', 'iconjet' ) }
						value={ url }
						onChange={ ( value ) =>
							setAttributes( { url: withScheme( value ) } )
						}
					/>
					{ url && (
						<>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __( 'Open in new tab', 'iconjet' ) }
								checked={ linkTarget === '_blank' }
								onChange={ toggleNewTab }
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Link rel', 'iconjet' ) }
								help={ __(
									'For example: nofollow, sponsored',
									'iconjet'
								) }
								value={ rel }
								onChange={ ( value ) =>
									setAttributes( { rel: value } )
								}
							/>
							{ ! label && (
								<Notice status="info" isDismissible={ false }>
									{ __(
										'Linked icons need an accessible label. Until you add one, the icon name is used:',
										'iconjet'
									) }{ ' ' }
									<strong>
										{ linkLabel( label, icon ) }
									</strong>
								</Notice>
							) }
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<span { ...boxProps( attributes ) }>
					<IconSvg
						icon={ svg }
						size={ size || defaultSize }
						label={ label }
						className={ `iconjet-icon iconjet-${ icon.split( ':' )[ 0 ] }` }
						transform={ getTransform(
							svg.v,
							rotation,
							flipHorizontal,
							flipVertical
						) }
						style={ { display: 'block' } }
					/>
				</span>
			</div>
			{ picker }
		</>
	);
}
