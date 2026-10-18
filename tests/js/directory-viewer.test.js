/**
 * Tests for the loading of the tree in the directory viewer (src/index.js).
 *
 * The REST endpoint is simulated: like the real endpoint it returns "directory_loading" as long as
 * not all directories are loaded and the tree at the end.
 */
import { act } from 'react';

/**
 * The modules, which are provided by WordPress in the backend.
 */
jest.mock( '@wordpress/api-fetch', () => ( { __esModule: true, default: jest.fn() } ), { virtual: true } );
jest.mock(
	'@wordpress/components',
	() => {
		const { createElement } = require( 'react' );
		const Control = ( props ) => createElement( 'div', { className: 'control' }, props.label );
		return {
			__esModule: true,
			Button: ( props ) => createElement( 'button', { onClick: props.onClick, disabled: props.disabled }, props.children ),
			ToggleControl: Control,
			SelectControl: Control,
			TextareaControl: Control,
			__experimentalInputControl: Control,
		};
	},
	{ virtual: true }
);

const apiFetch = require( '@wordpress/api-fetch' ).default;

/**
 * The texts the backend gets from PHP.
 */
const texts = {
	get_directory_endpoint: '/easy-directory-listing-for-wordpress/v1/directory',
	is_loading: 'Please wait, directory is loading.',
	cancel: 'Cancel',
	please_wait: 'Cancel loading, please wait',
	loading_directory: '1 sub-directory to load',
	loading_directories: '%1$d sub-directories to load',
	serverside_error: 'Incorrect response received from the server, possibly a server-side error.',
	error_title: 'The following error occurred:',
	errors_title: 'The following errors occurred:',
	empty_directory: 'Loaded an empty directory.',
	actions: 'Actions',
	filename: 'Filename',
	filesize: 'Size',
	date: 'Date',
	search_label: 'Search',
	search_placeholder: 'Search files …',
	search_in_directory: 'in %1$s',
	config_missing: 'Configuration missing!',
	nonce_missing: 'Nonce missing!',
	form_login: {
		save_credentials: { label: 'Save credentials' },
		button: { label: 'Connect' },
	},
};

/**
 * The simulated server.
 */
const server = {
	directories: 1,
	loaded: 0,
	requests: [],
	response: null,
};

/**
 * Simulate the REST endpoint: one directory per request.
 *
 * @param {Object} options The options of the request.
 * @return {Promise} The response.
 */
const simulateEndpoint = ( options ) => {
	server.requests.push( options.data );

	// return a configured response.
	if ( null !== server.response ) {
		return server.response();
	}

	// start a new loading process, if requested.
	if ( options.data.restart ) {
		server.loaded = 0;
	}
	server.loaded++;

	// tell the client to load further directories.
	if ( server.loaded < server.directories && ! options.data.cancelLoading ) {
		return Promise.resolve( { directory_loading: true, directory_to_load: server.directories - server.loaded } );
	}

	// return the tree.
	const dirs = {};
	for ( let i = 1; i < server.directories; i++ ) {
		dirs[ '/root/dir' + i + '/' ] = { title: 'dir' + i, files: [], dirs: {} };
	}
	return Promise.resolve( {
		'/root/': { title: 'root', files: [ { title: 'file.pdf', filesize: 1024, preview: '', icon: '' } ], dirs },
	} );
};

/**
 * Let React and the simulated requests finish their work.
 *
 * @param {number} rounds Amount of rounds.
 * @return {Promise<void>}
 */
const settle = async ( rounds = 50 ) => {
	for ( let i = 0; i < rounds; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
};

/**
 * Render the directory viewer with the given config.
 *
 * @param {Object} config The config.
 * @return {Promise<HTMLElement>} The container.
 */
const renderViewer = async ( config = {} ) => {
	document.getElementById( 'easy-directory-listing-for-wordpress' )?.remove();

	const container = document.createElement( 'div' );
	container.id = 'easy-directory-listing-for-wordpress';
	container.dataset.config = JSON.stringify( {
		nonce: 'nonce',
		fields: {},
		listing_base_object_name: 'local',
		actions: [],
		global_actions: [ { action: 'setTree( false );setLoadTree( ! loadTree );', label: 'Reload' } ],
		...config,
	} );
	document.body.appendChild( container );

	await act( async () => {
		document.body.dispatchEvent( new CustomEvent( 'easy-directory-listing-for-wordpress' ) );
	} );
	await settle();

	return container;
};

/**
 * Click the button with the given text.
 *
 * @param {HTMLElement} container The container.
 * @param {string}      text The text of the button.
 * @return {Promise<void>}
 */
const clickButton = async ( container, text ) => {
	const button = [ ...container.querySelectorAll( 'button' ) ].find( ( element ) => element.textContent === text );
	expect( button ).toBeDefined();
	await act( async () => {
		button.click();
	} );
};

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	global.edlfwJsVars = texts;
	global.wp = { i18n: { _n: ( single ) => single } };
	global.ReactDOM = require( 'react-dom/client' );

	// load the viewer like WordPress does it.
	require( '../../src/index' );
	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
} );

beforeEach( () => {
	server.directories = 1;
	server.loaded = 0;
	server.requests = [];
	server.response = null;
	apiFetch.mockReset();
	apiFetch.mockImplementation( simulateEndpoint );
} );

describe( 'loading of the tree', () => {
	test.each( [ 1, 2, 3, 4, 5, 6, 7, 8 ] )( 'loads a tree with %d directories with exactly as many requests', async ( directories ) => {
		server.directories = directories;

		const container = await renderViewer();

		// the tree is shown.
		expect( container.querySelector( '#easy-directory-listing-for-wordpress-listing' ) ).not.toBeNull();
		expect( container.querySelectorAll( '#easy-directory-listing-for-wordpress-listing li' ) ).toHaveLength( directories );

		// no further requests after the tree has been loaded (this was an endless loop for an even amount of directories).
		await settle( 100 );
		expect( apiFetch ).toHaveBeenCalledTimes( directories );
	} );

	test( 'starts a new loading process on the server with the first request only', async () => {
		server.directories = 4;

		await renderViewer();

		expect( server.requests.map( ( data ) => data.restart ) ).toEqual( [ true, false, false, false ] );
	} );

	test( 'sends the configuration with each request', async () => {
		await renderViewer( { nonce: 'my-nonce', listing_base_object_name: 'my-listing' } );

		expect( apiFetch ).toHaveBeenCalledWith( expect.objectContaining( { path: texts.get_directory_endpoint, method: 'POST' } ) );
		expect( server.requests[ 0 ] ).toEqual( expect.objectContaining( { nonce: 'my-nonce', listing_base_object_name: 'my-listing', cancelLoading: false } ) );
	} );

	test( 'reloads the tree exactly once via the global action', async () => {
		server.directories = 3;

		const container = await renderViewer();
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );

		await clickButton( container, 'Reload' );
		await settle( 100 );

		expect( apiFetch ).toHaveBeenCalledTimes( 6 );
		expect( server.requests.map( ( data ) => data.restart ) ).toEqual( [ true, false, false, true, false, false ] );
		expect( container.querySelector( '#easy-directory-listing-for-wordpress-listing' ) ).not.toBeNull();
	} );

	test( 'cancels the loading and shows the tree loaded so far', async () => {
		// answer the requests manually.
		const pending = [];
		server.response = () => new Promise( ( resolve ) => pending.push( resolve ) );
		const answer = async ( response ) => {
			await act( async () => {
				pending.shift()( response );
			} );
			await settle( 5 );
		};

		const container = await renderViewer();
		expect( container.textContent ).toContain( texts.is_loading );

		// the first request results in the hint to load further directories.
		await answer( { directory_loading: true, directory_to_load: 999 } );
		expect( server.requests ).toHaveLength( 2 );

		// cancel the loading while the second request is running.
		await clickButton( container, 'Cancel' );
		expect( container.textContent ).toContain( texts.please_wait );

		// the next request tells the server to cancel the loading.
		await answer( { directory_loading: true, directory_to_load: 998 } );
		expect( server.requests ).toHaveLength( 3 );
		expect( server.requests[ 1 ].cancelLoading ).toBe( false );
		expect( server.requests[ 2 ].cancelLoading ).toBe( true );

		// the server answers with the tree loaded so far.
		await answer( { '/root/': { title: 'root', files: [], dirs: {} } } );
		expect( container.querySelector( '#easy-directory-listing-for-wordpress-listing' ) ).not.toBeNull();
		expect( server.requests ).toHaveLength( 3 );
	} );

	test( 'does not request anything before the form has been submitted', async () => {
		const container = await renderViewer( { fields: { login: { type: 'text', label: 'Login', value: '' } }, form_title: 'Login' } );

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( container.textContent ).toContain( 'Login' );
	} );
} );

describe( 'errors during the loading', () => {
	test.each( [
		[ 'an empty array', [] ],
		[ 'null', null ],
		[ 'a string', 'error' ],
	] )( 'shows an error instead of the loading hint forever for %s as response', async ( name, response ) => {
		server.response = () => Promise.resolve( response );

		const container = await renderViewer();

		expect( container.textContent ).toContain( texts.serverside_error );
		expect( container.textContent ).not.toContain( texts.is_loading );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'shows the errors of the server', async () => {
		server.response = () => Promise.resolve( { errors: [ 'Login failed.', 'Directory not found.' ] } );

		const container = await renderViewer();

		expect( container.textContent ).toContain( texts.errors_title );
		expect( container.textContent ).toContain( 'Login failed.' );
		expect( container.textContent ).toContain( 'Directory not found.' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'shows the message of a failed request, e.g. an expired nonce', async () => {
		server.response = () => Promise.reject( { code: 'edlfw_invalid_nonce', message: 'The secure token is invalid or has expired.' } );

		const container = await renderViewer();

		expect( container.textContent ).toContain( texts.serverside_error );
		expect( container.textContent ).toContain( 'The secure token is invalid or has expired.' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'shows a hint without a nonce', async () => {
		const container = await renderViewer( { nonce: '' } );

		expect( container.textContent ).toContain( texts.nonce_missing );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
