<?php
/**
 * File to handle the serverside tasks for directory listing.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Object to handle the serverside tasks for directory listing.
 */
class Rest {
	/**
	 * The init object.
	 *
	 * @var Init
	 */
	private Init $init_obj;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Rest
	 */
	private static ?Rest $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Rest
	 */
	public static function get_instance(): Rest {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @param Init $init_obj The init object.
	 *
	 * @return void
	 */
	public function init( Init $init_obj ): void {
		// secure the init object.
		$this->init_obj = $init_obj;

		// initialize endpoints.
		add_action( 'rest_api_init', array( $this, 'add_endpoints' ) );
	}

	/**
	 * Register REST endpoints.
	 *
	 * @return void
	 */
	public function add_endpoints(): void {
		// endpoint to get directory contents per request.
		register_rest_route(
			'easy-directory-listing-for-wordpress/v1',
			'/directory/',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_directory' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Return the listing of requested local directory.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return string[]
	 */
	public function get_directory( WP_REST_Request $request ): array {
		// get params.
		$params = $request->get_params();

		// bail if directory param is missing.
		if ( empty( $params['directory'] ) ) {
			return array();
		}

		// bail if nonce param is missing.
		if ( empty( $params['nonce'] ) ) {
			return array();
		}

		// check the nonce value.
		if ( ! wp_verify_nonce( $params['nonce'], $this->get_init_obj()->get_nonce_name() ) ) {
			return array();
		}

		// bail if user is not logged in.
		if ( ! is_user_logged_in() ) {
			return array();
		}

		// get listing base object name.
		$listing_base_object_name = $params['listing_base_object_name'];

		// bail if no listing base is set.
		if ( empty( $listing_base_object_name ) ) {
			return array();
		}

		// get the object.
		$listing_base_object = false;
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			// bail if object is not from our base.
			if ( ! $obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// bail if names does not match.
			if ( $listing_base_object_name !== $obj->get_name() ) {
				continue;
			}

			$listing_base_object = $obj;
		}

		// bail if no listing object could be found.
		if ( ! $listing_base_object ) {
			return array();
		}

		// get the directory.
		$directory = $params['directory'];

		// get the login.
		$listing_base_object->set_login( $params['login'] );

		// get the password.
		$listing_base_object->set_password( $params['password'] );

		// get the API key.
		$listing_base_object->set_api_key( $params['api_key'] );

		// bail if login failed.
		if ( ! $listing_base_object->do_login( $directory ) ) {
			return array( 'errors' => $this->get_errors_for_response( $listing_base_object->get_errors() ) );
		}

		// get the directory listing and collect all files and directories as array.
		$subs = $listing_base_object->get_directory_listing( $directory );

		// build basic return array.
		$listing = array(
			array(
				'dir'   => $directory,
				'title' => basename( $directory ),
				'count' => count( $subs ),
				'sub'   => $subs,
			),
		);

		// bail if list is empty.
		if ( empty( $listing ) ) {
			return array();
		}

		/**
		 * Filter the resulting list of files and directories.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param array $listing The listing of directories and files.
		 * @param string $directory The base-directory used.
		 */
		return apply_filters( $this->get_init_obj()->get_prefix() . '_directory_listing', $listing, $directory );
	}

	/**
	 * Return the init object.
	 *
	 * @return Init
	 */
	private function get_init_obj(): Init {
		return $this->init_obj;
	}

	/**
	 * Return error texts from list of WP_Error objects.
	 *
	 * @param array $errors List of WP_Error objects.
	 *
	 * @return array
	 */
	private function get_errors_for_response( array $errors ): array {
		// collect the error texts.
		$error_texts = array();

		// loop through the errors and get its texts.
		foreach ( $errors as $error ) {
			// bail if object is not WP_Error.
			if ( ! $error instanceof WP_Error ) {
				continue;
			}

			// add the text to the list.
			$error_texts[] = $error->get_error_message();
		}

		// return resulting list of error texts.
		return $error_texts;
	}
}
