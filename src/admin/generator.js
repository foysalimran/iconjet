import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	Card,
	CardBody,
	CardHeader,
	ColorPalette,
	RangeControl,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { getQueryArg } from '@wordpress/url';

import IconPickerModal from '../shared/icon-picker';
import IconSvg from '../shared/svg';
import CopyButton from '../shared/copy-button';
import SocialProfiles from '../shared/social-profiles';
import { STYLE_OPTIONS } from '../shared/social-styles';
import { getPacks, getPlatforms } from '../shared/api';
import { withScheme } from '../shared/url';
import useShortcodePreview, { previewKey } from '../shared/use-preview';

const ALIGN_OPTIONS = [
	{ value: '', label: __( 'Default', 'iconjet' ) },
	{ value: 'left', label: __( 'Left', 'iconjet' ) },
	{ value: 'center', label: __( 'Center', 'iconjet' ) },
	{ value: 'right', label: __( 'Right', 'iconjet' ) },
];

/**
 * Make a value safe inside a double-quoted shortcode attribute: no quotes,
 * no square brackets (they would end the shortcode).
 *
 * @param {string} value Value.
 * @return {string} Safe value.
 */
function attrValue( value ) {
	return String( value )
		.replace( /"/g, '%22' )
		.replace( /\[/g, '%5B' )
		.replace( /\]/g, '%5D' );
}

function textValue( value ) {
	return String( value )
		.replace( /["[\]]/g, '' )
		.trim();
}

function buildShortcode( tag, atts ) {
	const parts = Object.keys( atts )
		.filter( ( key ) => atts[ key ] !== '' && atts[ key ] !== undefined )
		.map( ( key ) => `${ key }="${ atts[ key ] }"` );
	return `[${ [ tag, ...parts ].join( ' ' ) }]`;
}

function ColorField( { label, value, onChange } ) {
	return (
		<BaseControl
			__nextHasNoMarginBottom
			label={ label }
			id={ `iconjet-color-${ label }` }
		>
			<ColorPalette
				value={ value }
				onChange={ ( next ) => onChange( next || '' ) }
				enableAlpha={ false }
				clearable
				asButtons
				aria-label={ label }
			/>
		</BaseControl>
	);
}

function IconForm( { state, setState, defaultSize } ) {
	const [ isPickerOpen, setPickerOpen ] = useState( false );
	const set = ( changes ) => setState( { ...state, ...changes } );

	return (
		<>
			<BaseControl
				__nextHasNoMarginBottom
				label={ __( 'Icon', 'iconjet' ) }
				id="iconjet-generator-icon"
			>
				<div className="iconjet-generator__icon">
					{ state.svg && <IconSvg icon={ state.svg } size={ 32 } /> }
					<code>
						{ state.icon || __( 'No icon selected', 'iconjet' ) }
					</code>
					<Button
						id="iconjet-generator-icon"
						variant="secondary"
						onClick={ () => setPickerOpen( true ) }
						__next40pxDefaultSize
					>
						{ state.icon
							? __( 'Change icon', 'iconjet' )
							: __( 'Choose icon', 'iconjet' ) }
					</Button>
				</div>
			</BaseControl>
			{ isPickerOpen && (
				<IconPickerModal
					value={ state.icon }
					onClose={ () => setPickerOpen( false ) }
					onSelect={ ( { ref, svg } ) => {
						set( { icon: ref, svg } );
						setPickerOpen( false );
					} }
				/>
			) }
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Size (px)', 'iconjet' ) }
				min={ 8 }
				max={ 256 }
				value={ state.size || defaultSize }
				onChange={ ( value ) => set( { size: value || defaultSize } ) }
			/>
			<ColorField
				label={ __( 'Color', 'iconjet' ) }
				value={ state.color }
				onChange={ ( color ) => set( { color } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				type="url"
				label={ __( 'Link (optional)', 'iconjet' ) }
				value={ state.link }
				onChange={ ( link ) => set( { link } ) }
				onBlur={ () => set( { link: withScheme( state.link ) } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Open link in a new tab', 'iconjet' ) }
				checked={ state.newTab }
				disabled={ ! state.link }
				onChange={ ( newTab ) => set( { newTab } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Accessible label', 'iconjet' ) }
				help={ __(
					'Describe what the icon means. Leave empty for decorative icons. Linked icons use the icon name when empty.',
					'iconjet'
				) }
				value={ state.label }
				onChange={ ( label ) => set( { label } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Extra CSS classes (optional)', 'iconjet' ) }
				value={ state.className }
				onChange={ ( className ) => set( { className } ) }
			/>
		</>
	);
}

function SocialForm( { state, setState, platforms } ) {
	const set = ( changes ) => setState( { ...state, ...changes } );

	return (
		<>
			{ platforms ? (
				<SocialProfiles
					items={ state.items }
					platforms={ platforms }
					onChange={ ( items ) => set( { items } ) }
				/>
			) : (
				<Spinner />
			) }
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Style', 'iconjet' ) }
				value={ state.style }
				options={ STYLE_OPTIONS }
				onChange={ ( style ) => set( { style } ) }
			/>
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Size (px)', 'iconjet' ) }
				min={ 16 }
				max={ 128 }
				value={ state.size }
				onChange={ ( size ) => set( { size: size || 28 } ) }
			/>
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Spacing (px)', 'iconjet' ) }
				min={ 0 }
				max={ 64 }
				value={ state.gap }
				onChange={ ( gap ) => set( { gap: gap ?? 8 } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Alignment', 'iconjet' ) }
				value={ state.align }
				options={ ALIGN_OPTIONS }
				onChange={ ( align ) => set( { align } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Use brand colors', 'iconjet' ) }
				checked={ state.brandColors }
				onChange={ ( brandColors ) => set( { brandColors } ) }
			/>
			{ ! state.brandColors && (
				<ColorField
					label={
						state.style === 'icon'
							? __( 'Icon color', 'iconjet' )
							: __( 'Background color', 'iconjet' )
					}
					value={ state.color }
					onChange={ ( color ) => set( { color } ) }
				/>
			) }
			<ColorField
				label={ __( 'Hover color', 'iconjet' ) }
				value={ state.hoverColor }
				onChange={ ( hoverColor ) => set( { hoverColor } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Open links in a new tab', 'iconjet' ) }
				checked={ state.newTab }
				onChange={ ( newTab ) => set( { newTab } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Add rel="nofollow"', 'iconjet' ) }
				checked={ state.nofollow }
				onChange={ ( nofollow ) => set( { nofollow } ) }
			/>
		</>
	);
}

/**
 * Shortcode attributes for each mode. Default values are omitted.
 *
 * @param {string}  mode         icon | social.
 * @param {Object}  icon         Icon form state.
 * @param {Object}  social       Social form state.
 * @param {Object}  defs         Site defaults.
 * @param {boolean} forShortcode Escape values for the shortcode text (false: raw values for the preview).
 * @return {Object} Attributes.
 */
function getAtts( mode, icon, social, defs, forShortcode = true ) {
	const url = ( value ) => ( forShortcode ? attrValue( value ) : value );
	if ( mode === 'icon' ) {
		return {
			name: icon.icon,
			size:
				icon.size && icon.size !== defs.icon_size
					? String( icon.size )
					: '',
			color: icon.color,
			link: icon.link ? url( icon.link ) : '',
			label: textValue( icon.label ),
			new_tab: icon.link && icon.newTab ? 'yes' : '',
			class: textValue( icon.className ),
		};
	}
	const atts = {};
	social.items.forEach( ( item ) => {
		if ( item.url ) {
			atts[ item.id ] = url( item.url );
		}
	} );
	return {
		...atts,
		style: social.style !== defs.social_style ? social.style : '',
		brand_colors: social.brandColors ? '' : 'no',
		size: social.size !== 28 ? String( social.size ) : '',
		gap: social.gap !== 8 ? String( social.gap ) : '',
		color: social.brandColors ? '' : social.color,
		hover_color: social.hoverColor,
		new_tab: social.newTab ? '' : 'no',
		nofollow: social.nofollow ? 'yes' : '',
		align: social.align,
	};
}

export default function GeneratorPage() {
	const [ mode, setMode ] = useState( 'icon' );
	const [ defs, setDefs ] = useState( {
		icon_size: 24,
		social_style: 'circle',
	} );
	const [ platforms, setPlatforms ] = useState( null );
	const [ icon, setIcon ] = useState( {
		icon: getQueryArg( window.location.href, 'icon' ) || '',
		svg: null,
		size: 0,
		color: '',
		link: '',
		newTab: false,
		label: '',
		className: '',
	} );
	const [ social, setSocial ] = useState( {
		items: [],
		style: 'circle',
		size: 28,
		gap: 8,
		align: '',
		brandColors: true,
		color: '',
		hoverColor: '',
		newTab: true,
		nofollow: false,
	} );
	useEffect( () => {
		getPacks()
			.then( ( data ) => {
				setDefs( data.defaults );
				setSocial( ( s ) => ( {
					...s,
					style: data.defaults.social_style,
				} ) );
			} )
			.catch( () => {} );
		getPlatforms()
			.then( setPlatforms )
			.catch( () => setPlatforms( [] ) );
	}, [] );

	const atts = useMemo(
		() => getAtts( mode, icon, social, defs ),
		[ mode, icon, social, defs ]
	);
	const ready =
		mode === 'icon' ? !! icon.icon : social.items.some( ( i ) => i.url );
	const shortcode = ready
		? buildShortcode( mode === 'icon' ? 'iconjet' : 'iconjet_social', atts )
		: '';

	// Preview through the server's shortcode callbacks: the same renderer as
	// the frontend. The key is a string, so the preview is only requested
	// when the shortcode really changes.
	const request = ready
		? previewKey( mode, getAtts( mode, icon, social, defs, false ) )
		: '';
	const { html: preview, loading: previewing } =
		useShortcodePreview( request );

	return (
		<div className="iconjet-generator">
			<Card className="iconjet-generator__form">
				<TabPanel
					tabs={ [
						{
							name: 'icon',
							title: __( 'Icon [iconjet]', 'iconjet' ),
						},
						{
							name: 'social',
							title: __(
								'Social icons [iconjet_social]',
								'iconjet'
							),
						},
					] }
					initialTabName={ mode }
					onSelect={ setMode }
				>
					{ ( tab ) => (
						<CardBody>
							{ tab.name === 'icon' ? (
								<IconForm
									state={ icon }
									setState={ setIcon }
									defaultSize={ defs.icon_size }
								/>
							) : (
								<SocialForm
									state={ social }
									setState={ setSocial }
									platforms={ platforms }
								/>
							) }
						</CardBody>
					) }
				</TabPanel>
			</Card>

			<Card className="iconjet-generator__output">
				<CardHeader>
					<h2>{ __( 'Preview', 'iconjet' ) }</h2>
					{ previewing && <Spinner /> }
				</CardHeader>
				<CardBody>
					<div
						className="iconjet-generator__preview"
						aria-live="polite"
						// Markup from the server's shortcode callbacks, already escaped.
						dangerouslySetInnerHTML={ {
							__html:
								preview ||
								`<p class="description">${ __(
									'Choose an icon or add a profile URL to see a preview.',
									'iconjet'
								) }</p>`,
						} }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Shortcode', 'iconjet' ) }
						value={ shortcode }
						readOnly
						rows={ 3 }
					/>
					<CopyButton
						text={ shortcode }
						variant="primary"
						label={ __( 'Copy shortcode', 'iconjet' ) }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
