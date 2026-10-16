/**
 * Unit tests for the custom icon upload logic.
 */
import {
	MAX_BYTES,
	createQueue,
	createRows,
	errorMessage,
	updateRow,
	uploadFile,
} from '../upload';

const svg = ( name, size = 100 ) =>
	new window.File( [ 'x'.repeat( size ) ], name, { type: 'image/svg+xml' } );

const icon = ( name ) => ( { name, v: '0 0 24 24', b: '<path d="M0 0" />' } );

describe( 'uploadFile', () => {
	it( 'rejects non-SVG and oversized files without a request', async () => {
		const apiFetch = jest.fn();
		expect( ( await uploadFile( svg( 'a.txt' ), apiFetch ) ).status ).toBe(
			'rejected'
		);
		const big = await uploadFile( svg( 'a.svg', MAX_BYTES + 1 ), apiFetch );
		expect( big.status ).toBe( 'rejected' );
		expect( big.error ).toMatch( /Too large/ );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'accepts a valid icon response', async () => {
		const result = await uploadFile(
			svg( 'a.svg' ),
			jest.fn().mockResolvedValue( icon( 'a' ) )
		);
		expect( result ).toEqual( { status: 'accepted', icon: icon( 'a' ) } );
	} );

	it( 'reports the server error message', async () => {
		const result = await uploadFile(
			svg( 'a.svg' ),
			jest.fn().mockRejectedValue( {
				code: 'iconjet_svg_doctype',
				message: 'The file contains a DOCTYPE.',
			} )
		);
		expect( result ).toEqual( {
			status: 'rejected',
			error: 'The file contains a DOCTYPE.',
		} );
	} );

	it( 'explains unreadable, empty and network failures', async () => {
		const invalid = await uploadFile(
			svg( 'a.svg' ),
			jest.fn().mockRejectedValue( { code: 'invalid_json' } )
		);
		expect( invalid.error ).toMatch( /unreadable response/ );

		const empty = await uploadFile(
			svg( 'a.svg' ),
			jest.fn().mockResolvedValue( '' )
		);
		expect( empty.status ).toBe( 'rejected' );
		expect( empty.error ).toMatch( /did not contain the icon/ );

		const offline = await uploadFile(
			svg( 'a.svg' ),
			jest.fn().mockRejectedValue( { code: 'fetch_error' } )
		);
		expect( offline.error ).toMatch( /could not be reached/ );

		expect( errorMessage( undefined ) ).toMatch( /unknown reason/ );
	} );

	it( 'times out a request that never answers', async () => {
		const apiFetch = jest.fn(
			( { signal } ) =>
				new Promise( ( resolve, reject ) => {
					signal.addEventListener( 'abort', () => {
						const error = new Error( 'aborted' );
						error.name = 'AbortError';
						reject( error );
					} );
				} )
		);
		const result = await uploadFile( svg( 'a.svg' ), apiFetch, 10 );
		expect( result.status ).toBe( 'rejected' );
		expect( result.error ).toMatch( /timed out/ );
	} );
} );

describe( 'rows and queue', () => {
	it( 'gives every file a pending row immediately, with unique ids', () => {
		const rows = [
			...createRows( [ svg( 'a.svg' ) ] ),
			...createRows( [ svg( 'b.svg' ) ] ),
		];
		expect( rows.map( ( r ) => r.status ) ).toEqual( [
			'uploading',
			'uploading',
		] );
		expect( rows[ 0 ].id ).not.toBe( rows[ 1 ].id );
	} );

	it( 'keeps both batches when a second batch starts during the first', async () => {
		let release;
		const slow = new Promise( ( resolve ) => {
			release = resolve;
		} );
		const apiFetch = jest
			.fn()
			.mockImplementationOnce( () => slow.then( () => icon( 'a' ) ) )
			.mockImplementationOnce( () => Promise.resolve( icon( 'b' ) ) );
		const enqueue = createQueue( apiFetch );

		let rows = [];
		const onResult = ( id, result ) => {
			rows = updateRow( rows, id, result );
		};

		const first = createRows( [ svg( 'a.svg' ) ] );
		rows = [ ...rows, ...first ];
		const done1 = enqueue( first, onResult );

		const second = createRows( [ svg( 'b.svg' ) ] );
		rows = [ ...rows, ...second ];
		const done2 = enqueue( second, onResult );

		expect( rows.map( ( r ) => r.status ) ).toEqual( [
			'uploading',
			'uploading',
		] );
		release();
		await Promise.all( [ done1, done2 ] );

		expect( rows.map( ( r ) => [ r.file.name, r.status ] ) ).toEqual( [
			[ 'a.svg', 'accepted' ],
			[ 'b.svg', 'accepted' ],
		] );
		// Serial: the second request starts only after the first finished.
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );
} );
