/**
 * Shortcode Generator preview requests.
 */
import apiFetch from '@wordpress/api-fetch';
import { act } from 'react';
import { createRoot } from 'react-dom/client';

import GeneratorPage from '../generator';
import { PREVIEW_DELAY } from '../../shared/use-preview';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '../../shared/api', () => ( {
	getPacks: jest.fn( () =>
		Promise.resolve( {
			defaults: { icon_size: 24, social_style: 'circle' },
			packs: [],
		} )
	),
	getPlatforms: jest.fn( () => Promise.resolve( [] ) ),
} ) );

// The real picker loads whole packs; selecting an icon is all these tests need.
jest.mock( '../../shared/icon-picker', () => ( { onSelect } ) => (
	<button
		type="button"
		onClick={ () =>
			onSelect( {
				ref: 'lucide:heart',
				svg: { v: '0 0 24 24', b: '<path d="M0 0" />' },
			} )
		}
	>
		Pick heart
	</button>
) );

global.IS_REACT_ACT_ENVIRONMENT = true;

let container;
let root;

const previewCalls = () =>
	apiFetch.mock.calls.filter(
		( [ options ] ) => options.path === '/iconjet/v1/preview'
	);

const wait = ( ms ) =>
	act( async () => {
		jest.advanceTimersByTime( ms );
	} );

function button( text ) {
	return Array.from( container.querySelectorAll( 'button' ) ).find(
		( el ) => el.textContent === text
	);
}

async function click( el ) {
	await act( async () => {
		el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
	} );
}

// React tracks input values; set through the native setter so onChange fires.
async function type( input, value ) {
	const setter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	await act( async () => {
		setter.call( input, String( value ) );
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function selectHeart() {
	await renderGenerator();
	await click( button( 'Choose icon' ) );
	await click( button( 'Pick heart' ) );
}

/*
 * Render the page. The npm @wordpress/components (newer than WordPress
 * 7.1's) deprecates ColorPalette's asButtons for WordPress 7.2; core 6.6 to
 * 7.1 only support asButtons, so the plugin keeps it. The warning is logged
 * once per test run.
 */
async function renderGenerator() {
	await act( async () => {
		root.render( <GeneratorPage /> );
	} );
	// eslint-disable-next-line no-console -- jest-console mock.
	const warnings = console.warn.mock.calls;
	if ( warnings.length ) {
		expect( console ).toHaveWarned();
		expect( warnings[ 0 ][ 0 ] ).toContain(
			'`asButtons` prop in wp.components.ColorPalette'
		);
	}
}

beforeEach( () => {
	jest.useFakeTimers();
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( { data } ) =>
		Promise.resolve( { html: `<p>${ JSON.stringify( data.atts ) }</p>` } )
	);
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( async () => {
	await act( async () => root.unmount() );
	container.remove();
	jest.useRealTimers();
} );

describe( 'Shortcode Generator preview', () => {
	it( 'sends exactly one preview request when an icon is selected', async () => {
		await selectHeart();
		expect( previewCalls() ).toHaveLength( 0 );

		await wait( PREVIEW_DELAY );
		expect( previewCalls() ).toHaveLength( 1 );
		expect( previewCalls()[ 0 ][ 0 ].data ).toEqual( {
			type: 'icon',
			atts: { name: 'lucide:heart' },
		} );

		// No loop: nothing else is requested while nothing changes.
		await wait( 5000 );
		expect( previewCalls() ).toHaveLength( 1 );
		expect(
			container.querySelector( '.iconjet-generator__preview' ).innerHTML
		).toContain( 'lucide:heart' );
		expect( container.querySelector( '.components-spinner' ) ).toBeNull();
	} );

	it( 'sends one request after the size slider stops moving', async () => {
		await selectHeart();
		await wait( PREVIEW_DELAY );
		expect( previewCalls() ).toHaveLength( 1 );

		const size = container.querySelector( 'input[type="number"]' );
		for ( const value of [ 30, 40, 50, 60, 70, 80 ] ) {
			await type( size, value );
			await wait( 50 );
		}
		expect( previewCalls() ).toHaveLength( 1 );

		await wait( PREVIEW_DELAY );
		expect( previewCalls() ).toHaveLength( 2 );
		expect( previewCalls()[ 1 ][ 0 ].data.atts.size ).toBe( '80' );

		await wait( 5000 );
		expect( previewCalls() ).toHaveLength( 2 );
	} );

	it( 'keeps the newest preview when an older response arrives late', async () => {
		const pending = [];
		apiFetch.mockImplementation(
			( { data, signal } ) =>
				new Promise( ( resolve ) =>
					pending.push( { data, signal, resolve } )
				)
		);

		await selectHeart();
		await wait( PREVIEW_DELAY );
		await type( container.querySelector( 'input[type="number"]' ), 99 );
		await wait( PREVIEW_DELAY );
		expect( pending ).toHaveLength( 2 );
		expect( pending[ 0 ].signal.aborted ).toBe( true );

		const preview = container.querySelector(
			'.iconjet-generator__preview'
		);
		await act( async () => {
			pending[ 1 ].resolve( { html: '<p>new</p>' } );
		} );
		await act( async () => {
			pending[ 0 ].resolve( { html: '<p>old</p>' } );
		} );
		expect( preview.innerHTML ).toBe( '<p>new</p>' );
	} );

	it( 'keeps the previous preview visible while the next one loads', async () => {
		await selectHeart();
		await wait( PREVIEW_DELAY );
		const preview = container.querySelector(
			'.iconjet-generator__preview'
		);
		const first = preview.innerHTML;
		expect( first ).toContain( 'lucide:heart' );

		await type( container.querySelector( 'input[type="number"]' ), 64 );
		expect(
			container.querySelector( '.components-spinner' )
		).not.toBeNull();
		expect( preview.innerHTML ).toBe( first );
	} );
} );
