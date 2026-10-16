import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	DropZone,
	FormFileUpload,
	Modal,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';

import IconSvg from '../shared/svg';
import CopyButton from '../shared/copy-button';
import { clearCache } from '../shared/api';

// Mirrors Svg_Sanitizer::MAX_BYTES; the server checks again.
const MAX_BYTES = 51200;

function formatSize( bytes ) {
	return sprintf(
		/* translators: %s: file size in KB. */
		__( '%s KB', 'iconjet' ),
		( bytes / 1024 ).toFixed( 1 )
	);
}

/**
 * Upload one file. Resolves with { file, ok, icon?, error? }; never rejects.
 *
 * @param {File} file File.
 * @return {Promise<Object>} Result.
 */
function uploadFile( file ) {
	if ( ! /\.svg$/i.test( file.name ) ) {
		return Promise.resolve( {
			file,
			ok: false,
			error: __( 'Not an SVG file.', 'iconjet' ),
		} );
	}
	if ( file.size > MAX_BYTES ) {
		return Promise.resolve( {
			file,
			ok: false,
			error: sprintf(
				/* translators: 1: file size, 2: maximum size. */
				__( 'Too large (%1$s). The maximum is %2$s.', 'iconjet' ),
				formatSize( file.size ),
				formatSize( MAX_BYTES )
			),
		} );
	}
	const body = new window.FormData();
	body.append( 'file', file );
	return apiFetch( {
		path: '/iconjet/v1/custom-icons',
		method: 'POST',
		body,
	} )
		.then( ( icon ) => ( { file, ok: true, icon } ) )
		.catch( ( error ) => ( {
			file,
			ok: false,
			error:
				( error && error.message ) ||
				__( 'The upload failed.', 'iconjet' ),
		} ) );
}

export default function CustomIconsPage() {
	const [ icons, setIcons ] = useState( null );
	const [ results, setResults ] = useState( [] );
	const [ uploading, setUploading ] = useState( false );
	const [ loadError, setLoadError ] = useState( false );
	const [ editing, setEditing ] = useState( null );
	const [ deleting, setDeleting ] = useState( null );
	const [ notice, setNotice ] = useState( null );

	const load = useCallback( () => {
		clearCache( 'custom' );
		return apiFetch( { path: '/iconjet/v1/custom-icons' } )
			.then( ( pack ) =>
				setIcons(
					Object.keys( pack.icons ).map( ( name ) => ( {
						name,
						...pack.icons[ name ],
					} ) )
				)
			)
			.catch( () => setLoadError( true ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const upload = async ( fileList ) => {
		const files = Array.from( fileList || [] );
		if ( ! files.length ) {
			return;
		}
		setUploading( true );
		const done = [];
		// One at a time, so the server's icon limit is applied in order.
		for ( const file of files ) {
			done.push( await uploadFile( file ) );
			setResults( [ ...done ] );
		}
		setUploading( false );
		const accepted = done.filter( ( r ) => r.ok ).length;
		speak(
			sprintf(
				/* translators: 1: accepted count, 2: rejected count. */
				__( '%1$d accepted, %2$d rejected.', 'iconjet' ),
				accepted,
				done.length - accepted
			)
		);
		if ( accepted ) {
			load();
		}
	};

	const rename = ( event ) => {
		event.preventDefault();
		apiFetch( {
			path: `/iconjet/v1/custom-icons/${ editing.name }`,
			method: 'POST',
			data: { label: editing.label },
		} )
			.then( () => {
				setEditing( null );
				setNotice( {
					status: 'success',
					text: __( 'Icon renamed.', 'iconjet' ),
				} );
				load();
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	};

	const confirmDelete = () => {
		const icon = deleting;
		setDeleting( null );
		apiFetch( {
			path: `/iconjet/v1/custom-icons/${ icon.name }`,
			method: 'DELETE',
		} )
			.then( () => {
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: %s: icon name. */
						__( '“%s” was deleted.', 'iconjet' ),
						icon.l
					),
				} );
				load();
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	};

	return (
		<div className="iconjet-custom">
			<Card>
				<CardHeader>
					<h2>{ __( 'Upload SVG icons', 'iconjet' ) }</h2>
				</CardHeader>
				<CardBody>
					<div className="iconjet-custom__drop">
						<p>
							{ __(
								'Drop SVG files here, or choose them from your computer. Every file is sanitized: scripts, event handlers and external references are removed. Maximum 50 KB per file.',
								'iconjet'
							) }
						</p>
						<FormFileUpload
							accept=".svg,image/svg+xml"
							multiple
							onChange={ ( event ) => {
								upload( event.currentTarget.files );
								event.currentTarget.value = '';
							} }
							render={ ( { openFileDialog } ) => (
								<Button
									variant="primary"
									onClick={ openFileDialog }
									isBusy={ uploading }
									disabled={ uploading }
									accessibleWhenDisabled
									__next40pxDefaultSize
								>
									{ __( 'Choose SVG files', 'iconjet' ) }
								</Button>
							) }
						/>
						<DropZone
							label={ __(
								'Drop SVG files to upload',
								'iconjet'
							) }
							onFilesDrop={ upload }
						/>
					</div>

					{ results.length > 0 && (
						<ul
							className="iconjet-custom__results"
							aria-label={ __( 'Upload results', 'iconjet' ) }
						>
							{ results.map( ( result, index ) => (
								<li
									key={ index }
									className={ `iconjet-custom__result is-${
										result.ok ? 'accepted' : 'rejected'
									}` }
								>
									<span className="iconjet-custom__result-preview">
										{ result.ok && (
											<IconSvg
												icon={ result.icon }
												size={ 32 }
											/>
										) }
									</span>
									<span className="iconjet-custom__result-name">
										{ result.file.name }
									</span>
									<span className="iconjet-custom__result-status">
										{ result.ok
											? sprintf(
													/* translators: %s: icon reference. */
													__(
														'Accepted as %s (preview shows the sanitized result)',
														'iconjet'
													),
													`custom:${ result.icon.name }`
												)
											: sprintf(
													/* translators: %s: reason. */
													__(
														'Rejected: %s',
														'iconjet'
													),
													result.error
												) }
									</span>
								</li>
							) ) }
						</ul>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2>
						{ icons
							? sprintf(
									/* translators: %d: number of icons. */
									_n(
										'%d custom icon',
										'%d custom icons',
										icons.length,
										'iconjet'
									),
									icons.length
								)
							: __( 'Custom icons', 'iconjet' ) }
					</h2>
				</CardHeader>
				<CardBody>
					{ notice && (
						<Notice
							status={ notice.status }
							onRemove={ () => setNotice( null ) }
						>
							{ notice.text }
						</Notice>
					) }
					{ loadError && (
						<Notice status="error" isDismissible={ false }>
							{ __(
								'The icons could not be loaded.',
								'iconjet'
							) }
						</Notice>
					) }
					{ ! icons && ! loadError && <Spinner /> }
					{ icons && ! icons.length && (
						<p className="description">
							{ __(
								'No custom icons yet. Uploaded icons appear in the block picker, the Icon Library and the Shortcode Generator.',
								'iconjet'
							) }
						</p>
					) }
					{ icons && icons.length > 0 && (
						<ul className="iconjet-custom__grid">
							{ icons.map( ( icon ) => (
								<li
									key={ icon.name }
									className="iconjet-custom__card"
								>
									<div className="iconjet-custom__preview">
										<IconSvg icon={ icon } size={ 48 } />
									</div>
									{ editing && editing.name === icon.name ? (
										<form onSubmit={ rename }>
											<TextControl
												__nextHasNoMarginBottom
												__next40pxDefaultSize
												label={ __(
													'Name',
													'iconjet'
												) }
												help={ sprintf(
													/* translators: %s: icon reference. */
													__(
														'The reference %s stays the same, so existing content keeps working.',
														'iconjet'
													),
													`custom:${ icon.name }`
												) }
												value={ editing.label }
												onChange={ ( label ) =>
													setEditing( {
														...editing,
														label,
													} )
												}
											/>
											<div className="iconjet-custom__actions">
												<Button
													variant="primary"
													type="submit"
													size="compact"
												>
													{ __( 'Save', 'iconjet' ) }
												</Button>
												<Button
													variant="tertiary"
													size="compact"
													onClick={ () =>
														setEditing( null )
													}
												>
													{ __(
														'Cancel',
														'iconjet'
													) }
												</Button>
											</div>
										</form>
									) : (
										<>
											<strong className="iconjet-custom__label">
												{ icon.l }
											</strong>
											<code className="iconjet-custom__ref">
												{ `custom:${ icon.name }` }
											</code>
											<div className="iconjet-custom__actions">
												<CopyButton
													text={ `[iconjet name="custom:${ icon.name }"]` }
													label={ __(
														'Copy shortcode',
														'iconjet'
													) }
												/>
												<Button
													variant="tertiary"
													size="compact"
													onClick={ () =>
														setEditing( {
															name: icon.name,
															label: icon.l,
														} )
													}
													label={ sprintf(
														/* translators: %s: icon name. */
														__(
															'Rename %s',
															'iconjet'
														),
														icon.l
													) }
												>
													{ __(
														'Rename',
														'iconjet'
													) }
												</Button>
												<Button
													variant="tertiary"
													size="compact"
													isDestructive
													onClick={ () =>
														setDeleting( icon )
													}
													label={ sprintf(
														/* translators: %s: icon name. */
														__(
															'Delete %s',
															'iconjet'
														),
														icon.l
													) }
												>
													{ __(
														'Delete',
														'iconjet'
													) }
												</Button>
											</div>
										</>
									) }
								</li>
							) ) }
						</ul>
					) }
				</CardBody>
			</Card>

			{ deleting && (
				<Modal
					title={ sprintf(
						/* translators: %s: icon name. */
						__( 'Delete “%s”?', 'iconjet' ),
						deleting.l
					) }
					onRequestClose={ () => setDeleting( null ) }
					size="small"
				>
					<p>
						{ __(
							'Blocks that use this icon keep showing it, because blocks store their SVG. Shortcodes and Elementor widgets that use it will stop showing it. This cannot be undone.',
							'iconjet'
						) }
					</p>
					<div className="iconjet-custom__actions">
						<Button
							variant="tertiary"
							onClick={ () => setDeleting( null ) }
						>
							{ __( 'Cancel', 'iconjet' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ confirmDelete }
						>
							{ __( 'Delete icon', 'iconjet' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
