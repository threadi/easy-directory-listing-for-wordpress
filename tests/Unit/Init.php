<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Init.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit;

use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Init.
 */
class Init extends easyDirectoryListingTests {
	/**
	 * Return the object.
	 *
	 * @return \easyDirectoryListingForWordPress\Init
	 */
	private function get_obj(): \easyDirectoryListingForWordPress\Init {
		return \easyDirectoryListingForWordPress\Init::get_instance();
	}

	/**
	 * Test if the configuration of the embedding plugin is used.
	 *
	 * @return void
	 */
	public function test_configuration(): void {
		$this->assertEquals( EDLFW_TESTS_PREFIX, $this->get_obj()->get_prefix() );
		$this->assertEquals( EDLFW_TESTS_NONCE, $this->get_obj()->get_nonce_name() );
		$this->assertEquals( self::get_plugin_path(), $this->get_obj()->get_plugin_path() );
	}

	/**
	 * Test if all texts used in the backend are part of the translations.
	 *
	 * @return void
	 */
	public function test_translations(): void {
		$translations = $this->get_obj()->get_translations();
		foreach ( array( 'is_loading', 'nonce_missing', 'nonce_invalid', 'serverside_error', 'search_placeholder', 'reload' ) as $key ) {
			$this->assertArrayHasKey( $key, $translations );
			$this->assertNotEmpty( $translations[ $key ] );
		}
		$this->assertArrayHasKey( 'local', $translations['services'] );
	}

	/**
	 * Test if custom translations replace the basic translations recursively.
	 *
	 * @return void
	 */
	public function test_custom_translations(): void {
		$this->get_obj()->set_translations(
			array(
				'reload'   => 'Neu laden',
				'services' => array( 'local' => array( 'label' => 'Lokales Verzeichnis' ) ),
			)
		);
		try {
			$translations = $this->get_obj()->get_translations();
		} finally {
			$this->get_obj()->set_translations( array() );
		}

		$this->assertEquals( 'Neu laden', $translations['reload'] );
		$this->assertEquals( 'Lokales Verzeichnis', $translations['services']['local']['label'] );

		// not replaced texts are kept.
		$this->assertNotEmpty( $translations['cancel'] );
		$this->assertNotEmpty( $translations['services']['local']['title'] );
	}

	/**
	 * Test if the capabilities for the taxonomy use the capability.
	 *
	 * @return void
	 */
	public function test_capabilities(): void {
		$this->assertEquals( 'manage_options', $this->get_obj()->get_capability() );
		foreach ( $this->get_obj()->get_capabilities() as $capability ) {
			$this->assertEquals( 'manage_options', $capability );
		}
	}

	/**
	 * Test if the scripts are not loaded on other pages than the configured page.
	 *
	 * @return void
	 */
	public function test_scripts_only_on_configured_page(): void {
		$this->get_obj()->set_page_hook( 'media_page_edlfw_tests' );
		try {
			$this->get_obj()->add_scripts( 'index.php' );
		} finally {
			$this->get_obj()->set_page_hook( '' );
		}
		$this->assertFalse( wp_script_is( 'easy-directory-listing-for-wordpress', 'enqueued' ) );
	}

	/**
	 * Test if the config for the backend contains the necessary data.
	 *
	 * @return void
	 */
	public function test_config_of_listing(): void {
		$config = \easyDirectoryListingForWordPress\Listings\Local::get_instance()->get_config();

		$this->assertEquals( 'local', $config['listing_base_object_name'] );
		$this->assertNotEmpty( $config['nonce'] );
		$this->assertIsArray( $config['global_actions'] );
		$this->assertNotEmpty( $config['global_actions'] );
		$this->assertTrue( $config['archive'] );
	}
}
