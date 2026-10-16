/**
 * Custom icon upload logic, kept free of React so it can be unit tested.
 *
 * Guarantees:
 * - Every file gets a result row as soon as it is queued ("uploading"), and
 *   the row always ends as "accepted" or "rejected" with a reason, including
 *   for network errors, timeouts and responses that are not valid icon JSON.
 * - Rows are updated by id, so overlapping batches (a second drop while the
 *   first batch is still uploading) never overwrite each other.
 */
import { __, sprintf } from '@wordpress/i18n';

// Mirrors Svg_Sanitizer::MAX_BYTES; the server checks again.
export const MAX_BYTES = 51200;
export const TIMEOUT_MS = 30000;

let nextId = 0;

export function formatSize( bytes ) {
	return sprintf(
		/* translators: %s: file size in KB. */
		__( '%s KB', 'iconjet' ),
		( bytes / 1024 ).toFixed( 1 )
	);
}

/**
 * Pending result rows for new files.
 *
 * @param {File[]} files Files.
 * @return {Object[]} Rows { id, file, status: 'uploading' }.
 */
export function createRows( files ) {
	return files.map( ( file ) => ( {
		id: ++nextId,
		file,
		status: 'uploading',
	} ) );
}

/**
 * Replace one row by id.
 *
 * @param {Object[]} rows    Rows.
 * @param {number}   id      Row id.
 * @param {Object}   changes Changes.
 * @return {Object[]} New rows.
 */
export function updateRow( rows, id, changes ) {
	return rows.map( ( row ) =>
		row.id === id ? { ...row, ...changes } : row
	);
}

/**
 * Checks done in the browser before uploading.
 *
 * @param {File} file File.
 * @return {string} Error message, or '' when the file may be uploaded.
 */
export function precheck( file ) {
	if ( ! /\.svg$/i.test( file.name ) ) {
		return __( 'Not an SVG file.', 'iconjet' );
	}
	if ( file.size > MAX_BYTES ) {
		return sprintf(
			/* translators: 1: file size, 2: maximum size. */
			__( 'Too large (%1$s). The maximum is %2$s.', 'iconjet' ),
			formatSize( file.size ),
			formatSize( MAX_BYTES )
		);
	}
	return '';
}

/**
 * Turn any apiFetch rejection into a message a user can act on.
 *
 * @param {*} error Rejection value.
 * @return {string} Message.
 */
export function errorMessage( error ) {
	if ( error && error.name === 'AbortError' ) {
		return __( 'The upload timed out. Try again.', 'iconjet' );
	}
	if ( error && error.code === 'invalid_json' ) {
		return __(
			'The server sent an unreadable response. The file may or may not have been saved; reload the page to check.',
			'iconjet'
		);
	}
	if ( error && error.code === 'fetch_error' ) {
		return __(
			'The server could not be reached. Check your connection and try again.',
			'iconjet'
		);
	}
	if ( error && typeof error.message === 'string' && error.message ) {
		return error.message;
	}
	return __( 'The upload failed for an unknown reason.', 'iconjet' );
}

/**
 * Whether a success response really contains a stored icon.
 *
 * @param {*} icon Response.
 * @return {boolean} Valid.
 */
export function isIcon( icon ) {
	return !! (
		icon &&
		typeof icon.name === 'string' &&
		typeof icon.v === 'string' &&
		typeof icon.b === 'string'
	);
}

/**
 * Upload one file. Never rejects.
 *
 * @param {File}     file     File.
 * @param {Function} apiFetch @wordpress/api-fetch (injected for tests).
 * @param {number}   timeout  Timeout in ms.
 * @return {Promise<Object>} { status: 'accepted', icon } or { status: 'rejected', error }.
 */
export async function uploadFile( file, apiFetch, timeout = TIMEOUT_MS ) {
	const problem = precheck( file );
	if ( problem ) {
		return { status: 'rejected', error: problem };
	}

	const body = new window.FormData();
	body.append( 'file', file );
	const controller =
		typeof window.AbortController === 'function'
			? new window.AbortController()
			: null;
	const timer = controller
		? setTimeout( () => controller.abort(), timeout )
		: null;

	try {
		const icon = await apiFetch( {
			path: '/iconjet/v1/custom-icons',
			method: 'POST',
			body,
			signal: controller ? controller.signal : undefined,
		} );
		if ( ! isIcon( icon ) ) {
			return {
				status: 'rejected',
				error: __(
					'The server response did not contain the icon. Reload the page to check whether it was saved.',
					'iconjet'
				),
			};
		}
		return { status: 'accepted', icon };
	} catch ( error ) {
		return { status: 'rejected', error: errorMessage( error ) };
	} finally {
		if ( timer ) {
			clearTimeout( timer );
		}
	}
}

/**
 * Serial upload queue shared by all batches, so the server's icon limit is
 * applied in order and requests never race each other.
 *
 * @param {Function} apiFetch apiFetch.
 * @return {Function} enqueue( rows, onResult ) => Promise of all results.
 */
export function createQueue( apiFetch ) {
	let tail = Promise.resolve();
	return ( rows, onResult ) => {
		const results = rows.map( ( row ) => {
			tail = tail.then( async () => {
				const result = await uploadFile( row.file, apiFetch );
				onResult( row.id, result );
				return result;
			} );
			return tail;
		} );
		return Promise.all( results );
	};
}
