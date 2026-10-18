<?php
/**
 * File to handle the main object for each test class.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Init;
use ReflectionProperty;
use WP_Filesystem_Direct;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Object to handle the preparations for each test class.
 */
abstract class easyDirectoryListingTests extends WP_UnitTestCase {
	/**
	 * The route of the REST endpoint.
	 *
	 * @var string
	 */
	protected const ROUTE = '/easy-directory-listing-for-wordpress/v1/directory';

	/**
	 * List of temporary directories created by a test.
	 *
	 * @var array<int,string>
	 */
	private array $temp_directories = array();

	/**
	 * Prepare the test environment for each test class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// prepare to load just one time.
		if ( ! did_action( 'edlfw_test_preparation_loaded' ) ) {
			// enable error reporting.
			error_reporting( E_ALL );

			// create a pseudo-plugin, which embeds the package (used e.g. for the encryption of credentials).
			$content = '<?php
/**
 * Plugin Name:       PHP Unit Tests for Easy Directory Listing for WordPress
 * Description:       Embeds the package during the PHP Unit Tests for Easy Directory Listing for WordPress.
 * Requires PHP:      8.2
 * Version:           1.0.0
 * Author:            Your name
 * Text Domain:       php-unit-tests-for-easy-directory-listing
 *
 * @package php-unit-tests-for-easy-directory-listing
 */';
			$wp_filesystem = self::get_filesystem();
			$wp_filesystem->mkdir( dirname( self::get_plugin_path() ) );
			$wp_filesystem->put_contents( self::get_plugin_path(), $content );

			// mark as loaded.
			do_action( 'edlfw_test_preparation_loaded' );
		}
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// the listing objects are singletons: reset their state, which would otherwise be kept between the tests.
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			$this->reset_listing_object( $obj );
		}
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// delete the temporary directories of this test.
		foreach ( $this->temp_directories as $directory ) {
			self::get_filesystem()->delete( $directory, true );
		}
		$this->temp_directories = array();

		parent::tear_down();
	}

	/**
	 * Return the path for our pseudo-plugin during tests.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string {
		return EDLFW_TESTS_PLUGIN_FILE;
	}

	/**
	 * Return the hook name with the prefix used in the tests.
	 *
	 * @param string $name The name of the hook without prefix.
	 *
	 * @return string
	 */
	protected static function hook( string $name ): string {
		return Init::get_instance()->get_prefix() . '_' . $name;
	}

	/**
	 * Return the local WP Filesystem object.
	 *
	 * @return WP_Filesystem_Direct
	 */
	protected static function get_filesystem(): WP_Filesystem_Direct {
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new WP_Filesystem_Direct( false );
	}

	/**
	 * Reset the state of a listing object (errors, directory and fields).
	 *
	 * @param Directory_Listing_Base $obj The listing object.
	 *
	 * @return void
	 */
	protected function reset_listing_object( Directory_Listing_Base $obj ): void {
		foreach ( array( 'errors' => array(), 'directory' => '', 'fields' => array() ) as $property => $value ) {
			$reflection = new ReflectionProperty( Directory_Listing_Base::class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( $obj, $value );
		}

		// reset the cached mime types (they depend on the capabilities of the actual user).
		if ( property_exists( $obj, 'mime_types' ) ) {
			$reflection = new ReflectionProperty( $obj, 'mime_types' );
			$reflection->setAccessible( true );
			$reflection->setValue( $obj, null );
		}
	}

	/**
	 * Login in this session as a new administrator.
	 *
	 * @return int The user ID.
	 */
	protected function do_login(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	/**
	 * Logout from this session.
	 *
	 * @return void
	 */
	protected function do_logout(): void {
		wp_set_current_user( 0 );
	}

	/**
	 * Create a temporary directory with the given structure and return its path with trailing slash.
	 *
	 * The structure is an array: keys with a trailing slash are directories (with an array as content),
	 * all other keys are files (with a string as content).
	 *
	 * @param array<string,mixed> $structure The structure.
	 *
	 * @return string
	 */
	protected function create_directory( array $structure ): string {
		$base = trailingslashit( get_temp_dir() ) . 'edlfw-tests-' . wp_generate_password( 8, false ) . '/';
		wp_mkdir_p( $base );
		$this->temp_directories[] = $base;
		$this->create_structure( $base, $structure );
		return $base;
	}

	/**
	 * Create the given structure in the given directory.
	 *
	 * @param string              $directory The directory.
	 * @param array<string,mixed> $structure The structure.
	 *
	 * @return void
	 */
	private function create_structure( string $directory, array $structure ): void {
		foreach ( $structure as $name => $content ) {
			if ( str_ends_with( $name, '/' ) ) {
				wp_mkdir_p( $directory . $name );
				$this->create_structure( $directory . $name, is_array( $content ) ? $content : array() );
				continue;
			}
			file_put_contents( $directory . $name, (string) $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Create a small JPEG image.
	 *
	 * @param string $path The path for the image.
	 *
	 * @return void
	 */
	protected function create_image( string $path ): void {
		$image = imagecreatetruecolor( 64, 48 );
		imagefilledrectangle( $image, 0, 0, 32, 24, (int) imagecolorallocate( $image, 200, 50, 50 ) );
		imagejpeg( $image, $path );
		imagedestroy( $image );
	}

	/**
	 * Send a request to the REST endpoint of this package and return the response.
	 *
	 * Missing parameters are filled with the values the backend uses.
	 *
	 * @param array<string,mixed> $params The parameters.
	 *
	 * @return WP_REST_Response
	 */
	protected function request( array $params ): WP_REST_Response {
		$params = array_merge(
			array(
				'fields'                   => array(),
				'listing_base_object_name' => 'local',
				'saveCredentials'          => false,
				'nonce'                    => wp_create_nonce( EDLFW_TESTS_NONCE ),
				'term'                     => false,
				'cancelLoading'            => false,
				'restart'                  => true,
			),
			$params
		);

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Load a tree completely, like the backend does it: repeat the request as long as directories must be loaded.
	 *
	 * @param array<string,mixed> $params The parameters.
	 *
	 * @return array{tree: array<string,mixed>, requests: int}
	 */
	protected function load_tree( array $params ): array {
		$requests = 0;
		do {
			$params['restart'] = 0 === $requests;
			$data              = $this->request( $params )->get_data();
			++$requests;
		} while ( is_array( $data ) && ! empty( $data['directory_loading'] ) && $requests < 100 );

		$this->assertLessThan( 100, $requests, 'The loading of the tree does not end.' );
		$this->assertIsArray( $data );

		return array(
			'tree'     => $data,
			'requests' => $requests,
		);
	}
}
