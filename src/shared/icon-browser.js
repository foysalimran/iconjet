/**
 * Icon browser: pack list, search (name + tags), category filter, recently
 * used icons and a virtualized grid. Used by the block picker modal and by
 * the admin Icon Library.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import {
	useDeferredValue,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';

import IconSvg from './svg';
import VirtualGrid from './virtual-grid';
import { clearCache, getPacks, loadPack } from './api';
import { addRecent, getRecent } from './recent';
import './icon-picker.scss';

const RECENT = 'recent';

/**
 * @param {Object}   props
 * @param {Function} props.onSelect ( icon: { ref, name, v, b, a } ) => void
 * @param {string}   props.value    Selected icon reference.
 * @param {number}   props.height   Grid height in px.
 */
export default function IconBrowser( { onSelect, value, height = 400 } ) {
	const [ packs, setPacks ] = useState( null );
	const [ active, setActive ] = useState( () =>
		getRecent().length ? RECENT : null
	);
	const [ icons, setIcons ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const query = useDeferredValue( search.trim().toLowerCase() );

	useEffect( () => {
		// Always fresh: the enabled packs and custom icons may have changed
		// since the last time the browser was opened.
		clearCache( 'custom' );
		getPacks()
			.then( ( data ) => {
				setPacks( data.packs );
				setActive( ( current ) => {
					if ( current ) {
						return current;
					}
					const fromValue =
						value &&
						data.packs.find( ( p ) =>
							value.startsWith( p.id + ':' )
						);
					if ( fromValue ) {
						return fromValue.id;
					}
					return data.packs[ 0 ] ? data.packs[ 0 ].id : null;
				} );
			} )
			.catch( () =>
				setError(
					__( 'The icon packs could not be loaded.', 'iconjet' )
				)
			);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const pack = packs && packs.find( ( p ) => p.id === active );

	useEffect( () => {
		setCategory( '' );
		setError( null );
		if ( active === RECENT ) {
			setIcons( getRecent() );
			return undefined;
		}
		if ( ! pack ) {
			return undefined;
		}
		let cancelled = false;
		setIcons( null );
		loadPack( pack )
			.then( ( list ) => ! cancelled && setIcons( list ) )
			.catch(
				() =>
					! cancelled &&
					setError(
						__( 'This icon pack could not be loaded.', 'iconjet' )
					)
			);
		return () => {
			cancelled = true;
		};
	}, [ active, pack ] );

	const filtered = useMemo( () => {
		if ( ! icons ) {
			return [];
		}
		const terms = query ? query.split( /\s+/ ) : [];
		return icons.filter(
			( icon ) =>
				( ! category || ( icon.c && icon.c.includes( category ) ) ) &&
				terms.every( ( term ) =>
					( icon.hay || icon.name || icon.ref ).includes( term )
				)
		);
	}, [ icons, query, category ] );

	const categories =
		pack && pack.categories ? Object.entries( pack.categories ) : [];

	const select = ( icon ) => {
		addRecent( icon );
		onSelect( icon );
	};

	const renderCell = ( icon ) => {
		const name = icon.label || icon.name || icon.ref.split( ':' )[ 1 ];
		// A plain <button>: the components Button styles force
		// `svg { fill: currentColor }`, which would fill stroke icons.
		return (
			<button
				type="button"
				className={ `iconjet-picker__icon${
					icon.ref === value ? ' is-selected' : ''
				}` }
				aria-pressed={ icon.ref === value }
				onClick={ () => select( icon ) }
				aria-label={ name }
				title={ name }
			>
				<IconSvg icon={ icon } size={ 24 } />
			</button>
		);
	};

	let status = '';
	if ( icons ) {
		status =
			active === RECENT && ! icons.length
				? __( 'Icons you choose will appear here.', 'iconjet' )
				: sprintf(
						/* translators: %d: number of icons. */
						_n( '%d icon', '%d icons', filtered.length, 'iconjet' ),
						filtered.length
					);
	}

	return (
		<div className="iconjet-picker__layout">
			<nav
				className="iconjet-picker__packs"
				aria-label={ __( 'Icon packs', 'iconjet' ) }
			>
				<Button
					isPressed={ active === RECENT }
					onClick={ () => setActive( RECENT ) }
					className="iconjet-picker__pack"
				>
					{ __( 'Recently used', 'iconjet' ) }
				</Button>
				{ ! packs && ! error && <Spinner /> }
				{ packs &&
					packs.map( ( p ) => (
						<Button
							key={ p.id }
							isPressed={ active === p.id }
							onClick={ () => setActive( p.id ) }
							className="iconjet-picker__pack"
						>
							<span>{ p.label }</span>
							<span className="iconjet-picker__count">
								{ p.count }
							</span>
						</Button>
					) ) }
			</nav>

			<div className="iconjet-picker__main">
				<div className="iconjet-picker__filters">
					<SearchControl
						__nextHasNoMarginBottom
						value={ search }
						onChange={ setSearch }
						label={ __( 'Search icons', 'iconjet' ) }
						placeholder={ __( 'Search icons', 'iconjet' ) }
					/>
					{ categories.length > 0 && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Category', 'iconjet' ) }
							hideLabelFromVision
							value={ category }
							onChange={ setCategory }
							options={ [
								{
									value: '',
									label: __( 'All categories', 'iconjet' ),
								},
								...categories.map( ( [ id, label ] ) => ( {
									value: id,
									label,
								} ) ),
							] }
						/>
					) }
				</div>

				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }

				{ ! error && ! icons && active !== RECENT && (
					<div className="iconjet-picker__loading">
						<Spinner />
					</div>
				) }

				{ icons && (
					<>
						<p
							className="iconjet-picker__status"
							aria-live="polite"
						>
							{ status }
						</p>
						<VirtualGrid
							items={ filtered }
							renderCell={ renderCell }
							height={ height }
							resetKey={ `${ active }|${ query }|${ category }` }
							label={ __( 'Icons', 'iconjet' ) }
						/>
					</>
				) }

				{ pack && pack.license && (
					<p className="iconjet-picker__license">
						{ sprintf(
							/* translators: 1: pack name, 2: license name. */
							__( '%1$s – %2$s license', 'iconjet' ),
							pack.label,
							pack.license
						) }
					</p>
				) }
			</div>
		</div>
	);
}
