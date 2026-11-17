<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Crypt.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit;

use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Crypt.
 */
class Crypt extends easyDirectoryListingTests {
	/**
	 * List of errors reported via the error hook of the crypt package during a test.
	 *
	 * @var array<int,array{code:string,data:array<mixed>}>
	 */
	private array $reported = array();

	/**
	 * Prepare each test: collect the reported errors.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// initialize the crypt object: warnings of its initialization (e.g. about the place of the key) are no errors of a value.
		$this->get_obj()->get_method();

		$this->reported = array();
		add_action( $this->get_error_hook(), array( $this, 'collect_error' ), 10, 3 );
	}

	/**
	 * Collect a reported error.
	 *
	 * @param string              $code    The error code.
	 * @param string              $message The message.
	 * @param array<string,mixed> $data    The data.
	 *
	 * @return void
	 */
	public function collect_error( string $code, string $message, array $data ): void {
		$this->reported[] = array(
			'code' => $code,
			'data' => $data,
		);
	}

	/**
	 * Return the object to test.
	 *
	 * @return \easyDirectoryListingForWordPress\Crypt
	 */
	private function get_obj(): \easyDirectoryListingForWordPress\Crypt {
		return \easyDirectoryListingForWordPress\Crypt::get_instance();
	}

	/**
	 * Return the name of the error hook of the crypt package.
	 *
	 * @return string
	 */
	private function get_error_hook(): string {
		return dirname( plugin_basename( self::get_plugin_path() ) ) . '_crypt_error';
	}

	/**
	 * Return a value encrypted with another key, e.g. from a copy of another website.
	 *
	 * @param string $plain_text The plain text.
	 * @param string $context    The context.
	 *
	 * @return string
	 */
	private function get_value_with_other_key( string $plain_text, string $context = '' ): string {
		$crypt_obj = new \CryptForWordPress\Crypt( self::get_plugin_path() );
		$crypt_obj->set_slug( 'another-website-for-easy-directory-listing-tests' );
		return $crypt_obj->encrypt( $plain_text, $context );
	}

	/**
	 * Test a value with context.
	 *
	 * @return void
	 */
	public function test_round_trip_with_context(): void {
		$encrypted = $this->get_obj()->encrypt( 'secret', 'my_field' );
		$this->assertNotEmpty( $encrypted );
		$this->assertEquals( 'secret', $this->get_obj()->decrypt( $encrypted, 'my_field' ) );
		$this->assertEmpty( $this->reported );
	}

	/**
	 * Test that a value encrypted without context (e.g. by an older version) is readable with a context,
	 * without any reported error.
	 *
	 * @return void
	 */
	public function test_value_without_context_is_readable_with_context(): void {
		$encrypted = $this->get_obj()->encrypt( 'old secret' );
		$this->assertEquals( 'old secret', $this->get_obj()->decrypt( $encrypted, 'my_field' ) );
		$this->assertEmpty( $this->reported );

		// and still without context.
		$this->assertEquals( 'old secret', $this->get_obj()->decrypt( $encrypted ) );
		$this->assertEmpty( $this->reported );
	}

	/**
	 * Test that a value with context is bound to it, without the fallback.
	 *
	 * @return void
	 */
	public function test_value_is_bound_to_its_context(): void {
		$encrypted = $this->get_obj()->encrypt( 'secret', 'my_field' );

		// another context does not decrypt it and names the requested context.
		$this->assertEquals( '', $this->get_obj()->decrypt( $encrypted, 'other_field' ) );
		$this->assertNotEmpty( $this->reported );
		foreach ( $this->reported as $error ) {
			$this->assertEquals( 'other_field', $error['data']['context'] );
		}
	}

	/**
	 * Test that a value of another key reports its errors once, with the context.
	 *
	 * @return void
	 */
	public function test_value_of_other_key_reports_context(): void {
		// a value without context.
		$this->assertEquals( '', $this->get_obj()->decrypt( $this->get_value_with_other_key( 'foreign' ), 'option:my_key' ) );
		$this->assertNotEmpty( $this->reported );
		$codes = array_column( $this->reported, 'code' );
		$this->assertEquals( array_unique( $codes ), $codes, 'each error is reported once' );
		foreach ( $this->reported as $error ) {
			$this->assertEquals( 'option:my_key', $error['data']['context'] );
		}

		// a value with context.
		$this->reported = array();
		$this->assertEquals( '', $this->get_obj()->decrypt( $this->get_value_with_other_key( 'foreign', 'option:my_key' ), 'option:my_key' ) );
		$this->assertNotEmpty( $this->reported );
		$this->assertEquals( 'option:my_key', $this->reported[0]['data']['context'] );
	}

	/**
	 * Test that other listeners of the error hook stay registered after a decryption with context.
	 *
	 * @return void
	 */
	public function test_listeners_are_kept(): void {
		$this->get_obj()->decrypt( $this->get_obj()->encrypt( 'old secret' ), 'my_field' );
		$this->assertNotFalse( has_action( $this->get_error_hook(), array( $this, 'collect_error' ) ) );
	}

	/**
	 * Test empty values.
	 *
	 * @return void
	 */
	public function test_empty_value(): void {
		$this->assertEquals( '', $this->get_obj()->decrypt( '', 'my_field' ) );
		$this->assertEmpty( $this->reported );
	}

	/**
	 * Test the callbacks for settings.
	 *
	 * @return void
	 */
	public function test_setting_callbacks(): void {
		$save = $this->get_obj()->get_save_callback( 'option:my_setting' );
		$read = $this->get_obj()->get_read_callback( 'option:my_setting' );

		// the save callback of a setting gets 3 arguments (pre_update_option_*).
		$encrypted = $save( 'value', 'old value', 'my_setting' );
		$this->assertEquals( 'value', $read( $encrypted ) );
		$this->assertEquals( 'value', $this->get_obj()->decrypt( $encrypted, 'option:my_setting' ) );

		// values saved before.
		$this->assertEquals( 'old', $read( $this->get_obj()->encrypt( 'old' ) ) );

		// empty values and "0".
		$this->assertEquals( '', $save( '' ) );
		$this->assertEquals( '', $read( '' ) );
		$this->assertEquals( '0', $read( $save( '0' ) ) );
		$this->assertEmpty( $this->reported );
	}
}
