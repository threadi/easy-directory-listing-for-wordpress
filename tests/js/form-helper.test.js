/**
 * Tests for the form helper functions in src/helper.js.
 */
import {
	edlfw_is_field_filled,
	edlfw_check_field_groups,
	edlfw_is_form_completed,
} from '../../src/helper';

describe( 'edlfw_is_field_filled', () => {
	test.each( [
		[ undefined, false ],
		[ { type: 'text' }, false ],
		[ { type: 'text', value: '' }, false ],
		[ { type: 'text', value: null }, false ],
		[ { type: 'text', value: 'a' }, true ],
		[ { type: 'checkbox', value: true }, true ],
		[ { type: 'checkbox', value: false }, false ],
		[ { type: 'file', value: 42 }, true ],
		[ { type: 'file', value: '42' }, true ],
		[ { type: 'file', value: '' }, false ],
	] )( 'field %p is filled: %p', ( field, expected ) => {
		expect( edlfw_is_field_filled( field ) ).toBe( expected );
	} );

	test( 'a chosen, not uploaded file counts as value', () => {
		expect( edlfw_is_field_filled( { type: 'file', value: '' }, { name: 'list.csv' } ) ).toBe( true );
	} );

	test( 'a chosen file does not count for other field types', () => {
		expect( edlfw_is_field_filled( { type: 'url', value: '' }, { name: 'list.csv' } ) ).toBe( false );
	} );
} );

describe( 'edlfw_check_field_groups', () => {
	const fields = ( file = '', url = '' ) => ( {
		file: { type: 'file', one_of: 'source', value: file },
		url: { type: 'url', one_of: 'source', value: url },
		login: { type: 'text', not_required: true, value: '' },
	} );

	test( 'group without value is missing', () => {
		expect( edlfw_check_field_groups( fields() ) ).toEqual( { missing: [ 'source' ], multiple: [] } );
	} );

	test( 'group with one value is ok', () => {
		expect( edlfw_check_field_groups( fields( '', 'https://example.com/list.csv' ) ) ).toEqual( { missing: [], multiple: [] } );
		expect( edlfw_check_field_groups( fields(), { file: { name: 'list.csv' } } ) ).toEqual( { missing: [], multiple: [] } );
	} );

	test( 'group with more than one value is reported', () => {
		expect( edlfw_check_field_groups( fields( '12', 'https://example.com/list.csv' ) ) ).toEqual( { missing: [], multiple: [ 'source' ] } );
		expect( edlfw_check_field_groups( fields( '', 'https://example.com/list.csv' ), { file: { name: 'list.csv' } } ) ).toEqual( { missing: [], multiple: [ 'source' ] } );
	} );

	test( 'fields without group are ignored', () => {
		expect( edlfw_check_field_groups( { a: { type: 'text', value: '' } } ) ).toEqual( { missing: [], multiple: [] } );
		expect( edlfw_check_field_groups( undefined ) ).toEqual( { missing: [], multiple: [] } );
	} );
} );

describe( 'edlfw_is_form_completed', () => {
	test( 'required fields must be filled', () => {
		expect( edlfw_is_form_completed( { server: { type: 'url', value: '' } } ) ).toBe( false );
		expect( edlfw_is_form_completed( { server: { type: 'url', value: 'https://example.com' } } ) ).toBe( true );
	} );

	test( 'optional fields are ignored', () => {
		expect( edlfw_is_form_completed( { server: { type: 'url', value: 'https://example.com' }, login: { type: 'text', not_required: true } } ) ).toBe( true );
	} );

	test( 'one field of each group must be filled', () => {
		const fields = {
			file: { type: 'file', one_of: 'source', value: '' },
			url: { type: 'url', one_of: 'source', value: '' },
		};
		expect( edlfw_is_form_completed( fields ) ).toBe( false );
		expect( edlfw_is_form_completed( fields, { file: { name: 'list.csv' } } ) ).toBe( true );
		fields.url.value = 'https://example.com/list.csv';
		expect( edlfw_is_form_completed( fields ) ).toBe( true );
	} );
} );
