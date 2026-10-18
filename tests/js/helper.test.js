/**
 * Tests for the helper functions in src/helper.js.
 */
import {
	human_file_size,
	edlfw_text,
	edlfw_get_search_terms,
	edlfw_search_tree,
	edlfw_split_by_term,
} from '../../src/helper';

/**
 * Return a directory entry like the REST endpoint returns it.
 *
 * @param {string} title The title.
 * @param {Array}  files List of file names.
 * @param {Object} dirs  The sub-directories.
 * @return {Object} The entry.
 */
const entry = ( title, files = [], dirs = {} ) => ( {
	title,
	files: files.map( ( name ) => ( { title: name } ) ),
	dirs,
} );

/**
 * A tree with sub-directories for the search tests.
 */
const tree = {
	'/root/': entry( 'root', [ 'Report 2026.pdf', 'image.jpg' ], {
		'/root/a/': entry( 'a', [ 'report-draft.pdf', 'notes.txt' ], {
			'/root/a/deep/': entry( 'deep', [ 'old report.pdf' ] ),
		} ),
		'/root/b/': entry( 'b', [ 'photo.jpg' ] ),
	} ),
};

describe( 'human_file_size', () => {
	test.each( [
		[ 0, '0 B' ],
		[ 1023, '1023 B' ],
		[ 1024, '1.0 KiB' ],
		[ 1536, '1.5 KiB' ],
		[ 1024 * 1024, '1.0 MiB' ],
		[ 5 * 1024 * 1024 * 1024, '5.0 GiB' ],
	] )( 'formats %d bytes as %s', ( bytes, expected ) => {
		expect( human_file_size( bytes ) ).toBe( expected );
	} );

	test( 'uses SI units if requested', () => {
		expect( human_file_size( 1000, true ) ).toBe( '1.0 kB' );
		expect( human_file_size( 999, true ) ).toBe( '999 B' );
	} );

	test( 'does not show 1024.0 KiB for values just below 1 MiB', () => {
		expect( human_file_size( 1024 * 1024 - 1 ) ).toBe( '1.0 MiB' );
	} );

	test( 'uses the given decimal places', () => {
		expect( human_file_size( 1536, false, 2 ) ).toBe( '1.50 KiB' );
	} );
} );

describe( 'edlfw_text', () => {
	test( 'replaces numbered placeholders', () => {
		expect( edlfw_text( '%1$d hits in %2$d directories for “%3$s”', 5, 2, 'pdf' ) ).toBe( '5 hits in 2 directories for “pdf”' );
	} );

	test( 'replaces a placeholder several times', () => {
		expect( edlfw_text( '%1$s and %1$s', 'a' ) ).toBe( 'a and a' );
	} );

	test( 'keeps placeholders without value', () => {
		expect( edlfw_text( '%1$d of %2$d', 1 ) ).toBe( '1 of %2$d' );
	} );

	test( 'returns an empty string for missing texts', () => {
		expect( edlfw_text( undefined, 1 ) ).toBe( '' );
		expect( edlfw_text( null ) ).toBe( '' );
	} );
} );

describe( 'edlfw_get_search_terms', () => {
	test( 'returns nothing for too short searches', () => {
		expect( edlfw_get_search_terms( 'a', 2 ) ).toEqual( [] );
		expect( edlfw_get_search_terms( '   a  ', 2 ) ).toEqual( [] );
		expect( edlfw_get_search_terms( '', 2 ) ).toEqual( [] );
		expect( edlfw_get_search_terms( undefined, 2 ) ).toEqual( [] );
	} );

	test( 'returns the terms in lower case', () => {
		expect( edlfw_get_search_terms( 'Report PDF', 2 ) ).toEqual( [ 'report', 'pdf' ] );
	} );

	test( 'ignores multiple spaces', () => {
		expect( edlfw_get_search_terms( '  report    2026  ', 2 ) ).toEqual( [ 'report', '2026' ] );
	} );
} );

describe( 'edlfw_search_tree', () => {
	test( 'returns empty data for a tree, which is still loading', () => {
		for ( const loading of [ false, [], null, undefined ] ) {
			const data = edlfw_search_tree( loading, [ 'report' ] );
			expect( data.results ).toEqual( [] );
			expect( data.hits ).toBe( 0 );
			expect( data.directories ).toBe( 0 );
		}
	} );

	test( 'counts files and directories without search terms', () => {
		const data = edlfw_search_tree( tree, [] );
		expect( data.hits ).toBe( 0 );
		expect( data.files ).toBe( 6 );
		expect( data.directories ).toBe( 4 );
	} );

	test( 'finds files in all directories case-insensitive', () => {
		const data = edlfw_search_tree( tree, [ 'report' ] );
		expect( data.hits ).toBe( 3 );
		expect( data.hitDirectories ).toBe( 3 );
		expect( data.results.map( ( result ) => result.file.title ) ).toEqual( [ 'Report 2026.pdf', 'report-draft.pdf', 'old report.pdf' ] );
	} );

	test( 'requires all terms', () => {
		const data = edlfw_search_tree( tree, [ 'report', 'draft' ] );
		expect( data.hits ).toBe( 1 );
		expect( data.results[ 0 ].file.title ).toBe( 'report-draft.pdf' );
		expect( data.results[ 0 ].directory ).toBe( '/root/a/' );
	} );

	test( 'counts the hits per directory and per sub-tree', () => {
		const data = edlfw_search_tree( tree, [ 'report' ] );
		expect( data.counts ).toEqual( { '/root/': 1, '/root/a/': 1, '/root/a/deep/': 1, '/root/b/': 0 } );
		expect( data.subtree ).toEqual( { '/root/': 3, '/root/a/': 2, '/root/a/deep/': 1, '/root/b/': 0 } );
	} );

	test( 'returns the path of the directory relative to the first directory', () => {
		const labels = edlfw_search_tree( tree, [ 'report' ] ).results.map( ( result ) => result.label );
		expect( labels ).toEqual( [ '/', '/a/', '/a/deep/' ] );
	} );

	test( 'limits the results but counts all hits', () => {
		const data = edlfw_search_tree( tree, [ 'report' ], 2 );
		expect( data.results ).toHaveLength( 2 );
		expect( data.hits ).toBe( 3 );
	} );

	test( 'ignores invalid entries', () => {
		const data = edlfw_search_tree( { '/root/': entry( 'root', [ 'report.pdf' ], { '/root/broken/': null } ), '/other/': 'invalid' }, [ 'report' ] );
		expect( data.hits ).toBe( 1 );
		expect( data.directories ).toBe( 1 );
	} );
} );

describe( 'edlfw_split_by_term', () => {
	test( 'splits the title around the first occurrence of the term', () => {
		expect( edlfw_split_by_term( 'My Report report.pdf', 'report' ) ).toEqual( { before: 'My ', match: 'Report', after: ' report.pdf' } );
	} );

	test( 'returns the complete title without match', () => {
		expect( edlfw_split_by_term( 'image.jpg', 'report' ) ).toEqual( { before: 'image.jpg', match: '', after: '' } );
		expect( edlfw_split_by_term( 'image.jpg', '' ) ).toEqual( { before: 'image.jpg', match: '', after: '' } );
	} );

	test( 'handles missing titles', () => {
		expect( edlfw_split_by_term( undefined, 'report' ) ).toEqual( { before: '', match: '', after: '' } );
	} );
} );
