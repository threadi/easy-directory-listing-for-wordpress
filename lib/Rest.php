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
	 * Register REST API endpoints.
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
					return current_user_can( Init::get_instance()->get_capability() );
				},
			)
		);
	}

	/**
	 * Return the listing of requested local directory.
	 *
	 * As many directories as possible are loaded within one request (limited by a time budget).
	 * Only if the budget is exhausted, the partial tree is cached and the client requests the rest.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_directory( WP_REST_Request $request ): array|WP_Error {
		// bail if user is not logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'edlfw_not_logged_in', $this->get_translation( 'nonce_invalid' ), array( 'status' => 401 ) );
		}

		// get params.
		$params = $request->get_params();

		// bail if nonce is missing or does not match. This is checked before anything else is done.
		if ( empty( $params['nonce'] ) || ! is_string( $params['nonce'] ) || ! wp_verify_nonce( $params['nonce'], $this->get_init_obj()->get_nonce_name() ) ) {
			return new WP_Error( 'edlfw_invalid_nonce', $this->get_translation( 'nonce_invalid' ), array( 'status' => 403 ) );
		}

		// get term, if set.
		$term_id = ! empty( $params['term'] ) ? absint( $params['term'] ) : 0;
		if ( $term_id > 0 ) {
			// get the term data.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );

			// if term could be loaded, set the credentials.
			if ( ! empty( $term_data ) ) {
				$params['title']     = $term_data['title'];
				$params['directory'] = $term_data['directory'];
				$params['fields']    = $term_data['fields'];
			}
		}

		// get listing base object name.
		$listing_base_object_name = isset( $params['listing_base_object_name'] ) && is_string( $params['listing_base_object_name'] ) ? $params['listing_base_object_name'] : '';

		// bail if no listing base is set.
		if ( empty( $listing_base_object_name ) ) {
			return array();
		}

		// get the listing object.
		$listing_base_object = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $listing_base_object_name );

		// bail if no listing object could be found.
		if ( ! $listing_base_object ) {
			return array();
		}

		// bail if the actual user is not allowed to use this listing object.
		if ( ! current_user_can( $listing_base_object->get_permission_name() ) ) {
			return new WP_Error( 'edlfw_not_allowed', $this->get_translation( 'nonce_invalid' ), array( 'status' => 403 ) );
		}

		// get the fields from request which configures the listing object.
		$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();

		// set the fields on the listing object.
		$listing_base_object->set_fields( $fields );

		// set the directory on listing object, if set.
		$listing_base_object->set_directory( isset( $params['directory'] ) && is_string( $params['directory'] ) ? $params['directory'] : '' );

		// get the directory to load from listing object.
		$directory = $listing_base_object->get_directory();

		// bail if login failed.
		if ( ! $listing_base_object->do_login( $directory ) ) {
			return array( 'errors' => $this->get_errors_for_response( $listing_base_object->get_errors() ) );
		}

		// save the directory as directory archive if this is enabled and allowed for the actual configuration.
		if ( ! empty( $params['saveCredentials'] ) && $listing_base_object->is_archive_allowed() ) {
			Taxonomy::get_instance()->add( $listing_base_object->get_name(), $directory, $fields );
		}

		// get the cache key for the tree of this user and directory.
		$cache_key = $this->get_init_obj()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree';

		// get the cached tree for requested URL.
		$directory_list = get_transient( $cache_key );

		// directory list must be an array. It is ignored if the client starts a new loading process,
		// so an aborted loading process (e.g., closed browser tab) does not result in an outdated tree.
		if ( ! is_array( $directory_list ) || ! empty( $params['restart'] ) ) {
			$directory_list = array();
		}

		// whether the user wants to cancel the loading.
		$cancel_loading = ! empty( $params['cancelLoading'] );

		/**
		 * Filter the time budget in seconds for loading directories in a single request.
		 *
		 * If the budget is exhausted, the partial tree is cached and the client requests the next directories.
		 * Use a small value for slow external sources to prevent timeouts.
		 *
		 * @since 4.3.0 Available since 4.3.0.
		 * @param float $time_budget The time budget in seconds.
		 * @param string $name The service name.
		 */
		$time_budget = (float) apply_filters( $this->get_init_obj()->get_prefix() . '_directory_listing_time_budget', 3.0, $listing_base_object->get_name() );

		// get the queue of directories which must be loaded. The first request loads the main directory.
		$queue = empty( $directory_list ) ? array( $directory ) : $this->get_next_directory( $directory_list );
		$start = microtime( true );

		// load directories until all are loaded or the time budget is exhausted.
		for ( $index = 0; $index < count( $queue ); $index++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed,Squiz.PHP.DisallowSizeFunctionsInLoops.Found
			$directory_to_load = $queue[ $index ];

			// bail if this directory is already loaded.
			if ( isset( $directory_list[ $directory_to_load ] ) ) {
				continue;
			}

			// get the directory listing and collect all files and directories as array.
			$subs = $listing_base_object->get_directory_listing( $directory_to_load );

			// bail if any error has been submitted from directory listing object.
			if ( ! empty( $listing_base_object->get_errors() ) ) {
				// remove the cache for this request.
				delete_transient( $cache_key );

				// return the error list.
				return array( 'errors' => $this->get_errors_for_response( $listing_base_object->get_errors() ) );
			}

			// add the result of the loaded directory in the list.
			if ( isset( $subs['completed'] ) ) {
				// the listing object has returned the complete tree at once.
				$directory_list = $subs;
				break;
			} elseif ( isset( $subs[ $directory_to_load ] ) ) {
				$directory_list[ $directory_to_load ] = $subs[ $directory_to_load ];
			} elseif ( empty( $subs ) ) {
				// directory could not be read (e.g., missing permissions): add it as empty directory,
				// so it is marked as loaded and the tree can be built.
				$directory_list[ $directory_to_load ] = array(
					'title' => basename( untrailingslashit( $directory_to_load ) ),
					'files' => array(),
					'dirs'  => array(),
				);
			} else {
				$directory_list[ $directory_to_load ] = $subs;
			}

			// add the sub-directories of the loaded directory to the queue.
			$loaded_entry = $directory_list[ $directory_to_load ];
			if ( is_array( $loaded_entry ) && isset( $loaded_entry['dirs'] ) && is_array( $loaded_entry['dirs'] ) ) {
				foreach ( array_keys( $loaded_entry['dirs'] ) as $path ) {
					if ( ! isset( $directory_list[ $path ] ) ) {
						$queue[] = (string) $path;
					}
				}
			}

			// bail if the time budget is exhausted or the user wants to cancel the loading.
			if ( $cancel_loading || ( microtime( true ) - $start ) >= $time_budget ) {
				break;
			}
		}

		// check if all directories have been loaded.
		$next_directories  = $this->get_next_directory( $directory_list );
		$directory_loading = ! empty( $next_directories );

		/**
		 * Filter whether we load any further directory.
		 *
		 * @since 3.3.3 Available since 3.3.3.
		 * @param bool $directory_loading True if more directories should be loaded.
		 * @param array $directory_list The list of directories.
		 * @param string $directory The directory to load.
		 */
		$directory_loading = apply_filters( Init::get_instance()->get_prefix() . '_directory_listing_' . $listing_base_object->get_name() . '_directory_loading', $directory_loading, $directory_list, $directory );

		// bail if we must load further directories.
		if ( $directory_loading && ! $cancel_loading ) {
			// save the actual tree as user-specific transient for the next request.
			set_transient( $cache_key, $directory_list, DAY_IN_SECONDS );

			return array(
				'directory_loading' => true,
				'directory_to_load' => count( $next_directories ),
			);
		}

		// cleanup the listing.
		if ( isset( $directory_list['completed'] ) ) {
			unset( $directory_list['completed'] );
		}

		// set the name.
		$name = $listing_base_object->get_name();

		/**
		 * Filter the resulting tree of files and directories before we build the tree.
		 *
		 * @since 3.3.4 Available since 3.3.4.
		 *
		 * @param array $tree The tree of directories and files.
		 * @param string $directory The base-directory used.
		 * @param string $name The service name.
		 */
		$directory_list = apply_filters( $this->get_init_obj()->get_prefix() . '_directory_listing_before_tree_building', $directory_list, $directory, $name );

		// build the resulting tree.
		$tree = $this->build_tree( $directory_list, $directory );

		/**
		 * Filter the resulting tree of files and directories after the tree has been build.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param array $tree The tree of directories and files.
		 * @param string $directory The base-directory used.
		 * @param string $name The service name.         *
		 */
		$tree = apply_filters( $this->get_init_obj()->get_prefix() . '_directory_listing', $tree, $directory, $name );

		// remove the cache for this request.
		delete_transient( $cache_key );

		// return the resulting tree.
		return $tree;
	}

	/**
	 * Return a translated text by its key with fallback to the key itself.
	 *
	 * @param string $key The key.
	 *
	 * @return string
	 */
	private function get_translation( string $key ): string {
		$translations = $this->get_init_obj()->get_translations();
		return isset( $translations[ $key ] ) && is_string( $translations[ $key ] ) ? $translations[ $key ] : $key;
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
	 * @param array<int,mixed> $errors List of WP_Error objects.
	 *
	 * @return array<int,mixed>
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
			// texts may contain messages from external sources: allow only safe HTML (e.g. links).
			$error_texts[] = wp_kses_post( $error->get_error_message() );
		}

		// return resulting list of error texts.
		return $error_texts;
	}

	/**
	 * Return the list of directories in the tree, which are not loaded yet.
	 *
	 * @param array<int|string,mixed> $directory_list The tree.
	 *
	 * @return array<int,string>
	 */
	private function get_next_directory( array $directory_list ): array {
		// collect all directories which must be loaded.
		$next_directories = array();

		// loop through the list of directories.
		foreach ( $directory_list as $entry ) {
			// bail if no dirs exist.
			if ( ! is_array( $entry ) || ! isset( $entry['dirs'] ) || ! is_array( $entry['dirs'] ) ) {
				continue;
			}

			// loop through the sub directories of this directory.
			foreach ( array_keys( $entry['dirs'] ) as $path ) {
				// bail if this directory is already loaded.
				if ( isset( $directory_list[ $path ] ) ) {
					continue;
				}

				// use this directory.
				$next_directories[] = (string) $path;
			}
		}

		// return the list of directories to load.
		return $next_directories;
	}

	/**
	 * Build the tree.
	 *
	 * Each entry, which is not a sub-directory of another entry, is a root of the resulting tree.
	 * The main directory is always a root, also if a sub-directory links back to it.
	 *
	 * @param array<int|string,mixed> $directory_list The list of all directories.
	 * @param string                  $main_directory The requested main directory.
	 *
	 * @return array<string,mixed>
	 */
	private function build_tree( array $directory_list, string $main_directory = '' ): array {
		// collect all paths which are sub-directories of any entry.
		$sub_directories = array();
		foreach ( $directory_list as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['dirs'] ) || ! is_array( $entry['dirs'] ) ) {
				continue;
			}
			foreach ( array_keys( $entry['dirs'] ) as $path ) {
				$sub_directories[ $path ] = true;
			}
		}

		// collect the tree.
		$tree = array();

		// add all root entries with their children.
		foreach ( $directory_list as $url => $entry ) {
			// bail if this entry is not usable or a sub-directory of another entry.
			if ( ! is_array( $entry ) || ( isset( $sub_directories[ $url ] ) && (string) $url !== $main_directory ) ) {
				continue;
			}

			// add this element to the tree.
			$tree[ trailingslashit( (string) $url ) ] = $this->build_node( $directory_list, $entry, array( (string) $url => true ) );
		}

		// return resulting tree.
		return $tree;
	}

	/**
	 * Return a single entry of the tree with all its children.
	 *
	 * @param array<int|string,mixed> $directory_list The list of all directories.
	 * @param array<string,mixed>     $entry The entry.
	 * @param array<string,bool>      $parents List of parent paths to prevent endless recursion.
	 *
	 * @return array<string,mixed>
	 */
	private function build_node( array $directory_list, array $entry, array $parents ): array {
		// bail if entry does not have any sub-directories.
		if ( ! isset( $entry['dirs'] ) || ! is_array( $entry['dirs'] ) ) {
			$entry['dirs'] = array();
			return $entry;
		}

		// loop through the given dirs and add them with their children to the entry.
		foreach ( array_keys( $entry['dirs'] ) as $path ) {
			// bail if this directory is not loaded or already a parent.
			if ( ! isset( $directory_list[ $path ] ) || ! is_array( $directory_list[ $path ] ) || isset( $parents[ $path ] ) ) {
				continue;
			}

			// add the children.
			$entry['dirs'][ trailingslashit( (string) $path ) ] = $this->build_node( $directory_list, $directory_list[ $path ], $parents + array( (string) $path => true ) );
		}

		// return the resulting entry.
		return $entry;
	}
}
