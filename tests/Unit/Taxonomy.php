<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Taxonomy.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit;

use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;
use WP_Term;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Taxonomy.
 */
class Taxonomy extends easyDirectoryListingTests {
	/**
	 * Return the taxonomy object.
	 *
	 * @return \easyDirectoryListingForWordPress\Taxonomy
	 */
	private function get_obj(): \easyDirectoryListingForWordPress\Taxonomy {
		return \easyDirectoryListingForWordPress\Taxonomy::get_instance();
	}

	/**
	 * Test if the taxonomy is registered.
	 *
	 * @return void
	 */
	public function test_taxonomy_is_registered(): void {
		$this->assertTrue( taxonomy_exists( $this->get_obj()->get_name() ) );
		$this->assertFalse( get_taxonomy( $this->get_obj()->get_name() )->public );
	}

	/**
	 * Test if an entry could be added and is not added twice.
	 *
	 * @return void
	 */
	public function test_add(): void {
		$term_id = $this->get_obj()->add( 'local', '/directory/', array() );
		$this->assertGreaterThan( 0, $term_id );

		// the same directory of the same type is not added twice.
		$this->assertEquals( $term_id, $this->get_obj()->add( 'local', '/directory/', array( 'other' => array( 'value' => 'x' ) ) ) );

		// the same directory of another type is a new entry.
		$this->assertNotEquals( $term_id, $this->get_obj()->add( 'ftp', '/directory/', array() ) );
	}

	/**
	 * Test if an action is run after adding an entry.
	 *
	 * @return void
	 */
	public function test_action_after_add(): void {
		$added = 0;
		add_action(
			self::hook( 'directory_listing_added' ),
			static function ( int $term_id ) use ( &$added ) {
				$added = $term_id;
			}
		);

		$term_id = $this->get_obj()->add( 'local', '/directory/', array() );
		$this->assertEquals( $term_id, $added );
	}

	/**
	 * Test if the fields are saved encrypted and are returned decrypted.
	 *
	 * @return void
	 */
	public function test_fields_are_saved_encrypted(): void {
		$fields  = array(
			'login'    => array( 'value' => 'my-login' ),
			'password' => array( 'value' => 'my-password' ),
		);
		$term_id = $this->get_obj()->add( 'ftp', 'ftp://example.com/', $fields );

		// the saved value must not contain the credentials.
		$saved_value = get_term_meta( $term_id, 'fields', true );
		$this->assertIsString( $saved_value );
		$this->assertStringNotContainsString( 'my-password', $saved_value );
		$this->assertStringNotContainsString( 'my-login', $saved_value );

		// the entry must return them.
		$entry = $this->get_obj()->get_entry( $term_id );
		$this->assertEquals( 'ftp://example.com/', $entry['title'] );
		$this->assertEquals( 'ftp://example.com/', $entry['directory'] );
		$this->assertEquals( $fields, $entry['fields'] );
	}

	/**
	 * Test if saved fields, which could not be decrypted, are not overwritten by requesting the entry.
	 *
	 * @return void
	 */
	public function test_not_decryptable_fields_are_not_overwritten(): void {
		$term_id = $this->get_obj()->add( 'ftp', 'ftp://example.com/', array( 'login' => array( 'value' => 'my-login' ) ) );

		// simulate fields, which could not be decrypted (e.g., after a migration without the key).
		$broken_value = 'this-is-not-decryptable';
		update_term_meta( $term_id, 'fields', $broken_value );

		$entry = $this->get_obj()->get_entry( $term_id );
		$this->assertEmpty( $entry['fields'] );
		$this->assertEquals( $broken_value, get_term_meta( $term_id, 'fields', true ) );
	}

	/**
	 * Test if an unknown entry results in an empty array.
	 *
	 * @return void
	 */
	public function test_get_unknown_entry(): void {
		$this->assertEquals( array(), $this->get_obj()->get_entry( 999999 ) );

		// a term of another taxonomy is not an entry.
		$this->assertEquals( array(), $this->get_obj()->get_entry( self::factory()->category->create() ) );
	}

	/**
	 * Test if an entry could be deleted.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$term_id = $this->get_obj()->add( 'local', '/directory/', array() );

		$this->assertTrue( $this->get_obj()->delete( $term_id ) );
		$this->assertNotInstanceOf( WP_Term::class, get_term( $term_id, $this->get_obj()->get_name() ) );

		// an unknown entry could not be deleted.
		$this->assertFalse( $this->get_obj()->delete( $term_id ) );
	}

	/**
	 * Test if the uninstallation removes all entries with their meta-data (which contains the encrypted credentials).
	 *
	 * @return void
	 */
	public function test_uninstall_removes_entries_and_meta(): void {
		global $wpdb;

		$term_ids = array(
			$this->get_obj()->add( 'ftp', 'ftp://example.com/', array( 'password' => array( 'value' => 'secret' ) ) ),
			$this->get_obj()->add( 'local', '/directory/', array() ),
		);
		$category_id = self::factory()->category->create();

		$this->get_obj()->uninstall();

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		// phpcs:disable WordPress.DB
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id IN ($placeholders)", $term_ids ) ) );
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id IN ($placeholders)", $term_ids ) ) );
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $this->get_obj()->get_name() ) ) );

		// terms of other taxonomies are kept.
		$this->assertEquals( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $category_id ) ) );
		// phpcs:enable WordPress.DB
	}

	/**
	 * Test if the table columns are set.
	 *
	 * @return void
	 */
	public function test_set_table_columns(): void {
		$columns = $this->get_obj()->set_table_columns(
			array(
				'cb'          => '<input type="checkbox">',
				'name'        => 'Name',
				'description' => 'Description',
				'slug'        => 'Slug',
			)
		);
		$this->assertEquals( array( 'cb', 'name', 'type', 'connect' ), array_keys( $columns ) );

		// an empty list stays empty.
		$this->assertEquals( array(), $this->get_obj()->set_table_columns( array() ) );
	}

	/**
	 * Test if the content of the type and connect column is set.
	 *
	 * @return void
	 */
	public function test_set_table_column_content(): void {
		$term_id = $this->get_obj()->add( 'local', '/directory/', array() );

		$this->assertEquals( \easyDirectoryListingForWordPress\Listings\Local::get_instance()->get_label(), $this->get_obj()->set_table_column_content( '', 'type', $term_id ) );
		$this->assertStringContainsString( 'term=' . $term_id, $this->get_obj()->set_table_column_content( '', 'connect', $term_id ) );

		// other columns are not changed.
		$this->assertEquals( 'content', $this->get_obj()->set_table_column_content( 'content', 'other', $term_id ) );
	}

	/**
	 * Test if the row actions contain the connect action and only the allowed actions.
	 *
	 * @return void
	 */
	public function test_set_actions(): void {
		$term_id = $this->get_obj()->add( 'local', '/directory/', array() );
		$term    = get_term( $term_id, $this->get_obj()->get_name() );

		$actions = $this->get_obj()->set_actions(
			array(
				'edit'   => 'edit',
				'delete' => 'delete',
				'view'   => 'view',
			),
			$term
		);
		$this->assertEquals( array( 'edit', 'delete', 'connect' ), array_keys( $actions ) );
	}

	/**
	 * Test if the custom messages are added.
	 *
	 * @return void
	 */
	public function test_add_custom_updated_messages(): void {
		$messages = $this->get_obj()->add_custom_updated_messages( array() );
		$this->assertArrayHasKey( $this->get_obj()->get_name(), $messages );
		$this->assertArrayHasKey( 2, $messages[ $this->get_obj()->get_name() ] );
		$this->assertArrayHasKey( 3, $messages[ $this->get_obj()->get_name() ] );
	}
}
