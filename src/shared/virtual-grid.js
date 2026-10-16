/**
 * Virtualized grid: only the rows in view (plus a small overscan) are in the
 * DOM, so packs with thousands of icons scroll smoothly.
 */
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

const OVERSCAN_ROWS = 3;

/**
 * @param {Object}   props
 * @param {Array}    props.items      Items to show.
 * @param {Function} props.renderCell ( item, index ) => element.
 * @param {number}   props.cellSize   Row height and minimum column width, px.
 * @param {number}   props.height     Viewport height, px.
 * @param {string}   props.resetKey   Scroll back to the top when this changes.
 * @param {string}   props.label      Accessible label for the grid region.
 */
export default function VirtualGrid( {
	items,
	renderCell,
	cellSize = 64,
	height = 400,
	resetKey,
	label,
} ) {
	const ref = useRef();
	const [ width, setWidth ] = useState( 0 );
	const [ scrollTop, setScrollTop ] = useState( 0 );

	useLayoutEffect( () => {
		const node = ref.current;
		if ( ! node ) {
			return undefined;
		}
		setWidth( node.clientWidth );
		const observer = new window.ResizeObserver( () =>
			setWidth( node.clientWidth )
		);
		observer.observe( node );
		return () => observer.disconnect();
	}, [] );

	useEffect( () => {
		if ( ref.current ) {
			ref.current.scrollTop = 0;
		}
		setScrollTop( 0 );
	}, [ resetKey ] );

	const columns = Math.max( 1, Math.floor( width / cellSize ) );
	const columnWidth = width / columns;
	const rows = Math.ceil( items.length / columns );
	const firstRow = Math.max(
		0,
		Math.floor( scrollTop / cellSize ) - OVERSCAN_ROWS
	);
	const lastRow = Math.min(
		rows,
		Math.ceil( ( scrollTop + height ) / cellSize ) + OVERSCAN_ROWS
	);

	const cells = [];
	if ( width ) {
		for ( let row = firstRow; row < lastRow; row++ ) {
			for ( let col = 0; col < columns; col++ ) {
				const index = row * columns + col;
				if ( index >= items.length ) {
					break;
				}
				cells.push(
					<div
						key={ index }
						className="iconjet-grid__cell"
						style={ {
							top: row * cellSize,
							insetInlineStart: col * columnWidth,
							width: columnWidth,
							height: cellSize,
						} }
					>
						{ renderCell( items[ index ], index ) }
					</div>
				);
			}
		}
	}

	return (
		<div
			ref={ ref }
			className="iconjet-grid"
			style={ { height } }
			onScroll={ ( event ) =>
				setScrollTop( event.currentTarget.scrollTop )
			}
			role="region"
			aria-label={ label }
			tabIndex={ -1 }
		>
			<div
				className="iconjet-grid__inner"
				style={ { height: rows * cellSize } }
			>
				{ cells }
			</div>
		</div>
	);
}
