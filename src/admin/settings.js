import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

import { clearCache } from '../shared/api';
import { STYLE_OPTIONS } from '../shared/social-styles';

export default function SettingsPage() {
	const [ settings, setSettings ] = useState( null );
	const [ packs, setPacks ] = useState( [] );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/wp/v2/settings' } ),
			apiFetch( { path: '/iconjet/v1/packs?all=1' } ),
		] )
			.then( ( [ wpSettings, packData ] ) => {
				setSettings( wpSettings.iconjet_settings );
				setPacks( packData.packs.filter( ( p ) => p.id !== 'custom' ) );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					text: __( 'The settings could not be loaded.', 'iconjet' ),
				} )
			);
	}, [] );

	if ( ! settings ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const update = ( changes ) => setSettings( { ...settings, ...changes } );
	const togglePack = ( id, enabled ) =>
		update( { packs: { ...settings.packs, [ id ]: enabled } } );

	const save = ( event ) => {
		event.preventDefault();
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { iconjet_settings: settings },
		} )
			.then( ( response ) => {
				setSettings( response.iconjet_settings );
				clearCache();
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'iconjet' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text:
						error.message ||
						__( 'The settings could not be saved.', 'iconjet' ),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<form className="iconjet-settings" onSubmit={ save }>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2>{ __( 'Icon packs', 'iconjet' ) }</h2>
				</CardHeader>
				<CardBody>
					<p className="description">
						{ __(
							'Disabled packs are hidden from the icon pickers and Elementor. Icons already used in your content keep displaying.',
							'iconjet'
						) }
					</p>
					{ packs.map( ( pack ) => (
						<ToggleControl
							key={ pack.id }
							__nextHasNoMarginBottom
							label={ `${ pack.label } (${ pack.count })` }
							help={ `${ pack.style } · ${ pack.license }` }
							checked={ !! settings.packs[ pack.id ] }
							onChange={ ( value ) =>
								togglePack( pack.id, value )
							}
						/>
					) ) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2>{ __( 'Defaults', 'iconjet' ) }</h2>
				</CardHeader>
				<CardBody>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Default icon size (px)', 'iconjet' ) }
						help={ __(
							'Used for new Icon blocks and for [iconjet] shortcodes without a size.',
							'iconjet'
						) }
						min={ 8 }
						max={ 512 }
						value={ settings.icon_size }
						onChange={ ( value ) =>
							update( { icon_size: value || 24 } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Default social icon style', 'iconjet' ) }
						help={ __(
							'Used for new Social Icons blocks and for [iconjet_social] shortcodes without a style.',
							'iconjet'
						) }
						value={ settings.social_style }
						options={ STYLE_OPTIONS }
						onChange={ ( value ) =>
							update( { social_style: value } )
						}
					/>
				</CardBody>
			</Card>

			<Button
				variant="primary"
				type="submit"
				isBusy={ saving }
				disabled={ saving }
				accessibleWhenDisabled
				__next40pxDefaultSize
			>
				{ __( 'Save settings', 'iconjet' ) }
			</Button>
		</form>
	);
}
