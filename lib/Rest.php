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
     * @param WP_REST_Request $request The request object.
     *
     * @return array<string,mixed>
     */
    public function get_directory( WP_REST_Request $request ): array {
        // bail if user is not logged in.
        if ( ! is_user_logged_in() ) {
            return array();
        }

        // get params.
        $params = $request->get_params();

        // get term, if set.
        $term_id = ! empty( $params['term'] ) ? absint( $params['term'] ) : 0;
        if ( $term_id > 0 ) {
            // get the term data.
            $term_data = Taxonomy::get_instance()->get_entry( $term_id );

            // if term could be loaded, set the credentials.
            if ( ! empty( $term_data ) ) {
                $params['title'] = $term_data['title'];
                $params['directory'] = get_term_meta( $term_id, 'path', true );
                $params['login']     = $term_data['login'];
                $params['password']  = $term_data['password'];
                $params['api_key']   = $term_data['api_key'];
            }
        }

        // bail if directory param is missing.
        if ( empty( $params['directory'] ) ) {
            return array();
        }

        // bail if nonce param is missing.
        if ( empty( $params['nonce'] ) ) {
            return array();
        }

        // bail if nonce value does not match.
        if ( ! wp_verify_nonce( $params['nonce'], $this->get_init_obj()->get_nonce_name() ) ) {
            return array();
        }

        // set title, if not set.
        if ( empty( $params['title'] ) ) {
            $params['title'] = basename( $params['directory'] );
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

        // save the directory as directory archive if this is enabled.
        if ( $params['saveCredentials'] ) {
            // get the taxonomy object.
            $taxonomy_obj = Taxonomy::get_instance();

            // add the credentials.
            $taxonomy_obj->add( $listing_base_object->get_name(), $params['directory'], $params['login'], $params['password'], $params['api_key'] );
        }

        // get the cached tree for requested URL.
        $directory_list = get_transient( $this->get_init_obj()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree' );

        // directory list must be an array.
        if ( ! is_array( $directory_list ) ) {
            $directory_list = array();
        }

        // check how many directories in the tree must be loaded and which one next.
        $next_directories = $this->get_next_directory( $directory, $directory_list );

        // mark which directory to load.
        $directory_to_load = $directory;

        // get the next directory from list, if set.
        if ( ! empty( $next_directories ) ) {
            $directory_to_load = array_shift( $next_directories );
        }

        // get the directory listing and collect all files and directories as array.
        $subs = $listing_base_object->get_directory_listing( $directory_to_load );

        // bail if any error has been submitted from directory listing object.
        if( ! empty( $listing_base_object->get_errors() ) ) {
            // remove the cache for this request.
            delete_transient( $this->get_init_obj()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree' );

            // return the error list.
            return array( 'errors' => $this->get_errors_for_response( $listing_base_object->get_errors() ) );
        }

        // add the result of the loaded directory in the list.
        if ( isset( $subs['completed'] ) ) {
            $directory_list = $subs;
        } elseif ( isset( $subs[ $directory_to_load ] ) ) {
            $directory_list[ $directory_to_load ] = $subs[ $directory_to_load ];
        } else {
            $directory_list[ $directory_to_load ] = $subs;
        }

        // save the actual tree as user-specific transient.
        set_transient( $this->get_init_obj()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree', $directory_list, DAY_IN_SECONDS );

        // check if all directories have been loaded.
        $directory_loading = false;
        foreach ( $directory_list as $entry ) {
            // bail if loading is already enabled.
            if ( $directory_loading ) {
                continue;
            }

            // bail if no directories are given.
            if ( ! isset( $entry['dirs'] ) ) {
                continue;
            }

            // loop through the directories.
            foreach ( $entry['dirs'] as $path => $sub ) {
                // bail if loading is already enabled.
                if ( $directory_loading ) {
                    continue;
                }

                // bail if URL is already loaded.
                if ( isset( $directory_list[ $path ] ) ) {
                    continue;
                }

                // mark that we must load more directories.
                $directory_loading = true;
            }
        }

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
        if ( $directory_loading && ! $params['cancelLoading'] ) {
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
        $tree = $this->build_tree( $directory_list );

        // use the configured title.
        $tree[ trailingslashit( $directory )]['title'] = $params['title'];

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
        delete_transient( $this->get_init_obj()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree' );

        // return the resulting tree.
        return $tree;
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
            $error_texts[] = $error->get_error_message();
        }

        // return resulting list of error texts.
        return $error_texts;
    }

    /**
     * Recursive check for next directory in tree.
     *
     * @param string              $directory The main directory.
     * @param array<string,mixed> $directory_list The tree.
     *
     * @return array<int,string>
     */
    private function get_next_directory( string $directory, array $directory_list ): array {
        // collect all directories which must be loaded.
        $next_directories = array();

        // loop through the list of directories.
        foreach ( $directory_list as $entry ) {
            // bail if no dirs exist.
            if ( ! isset( $entry['dirs'] ) ) {
                continue;
            }

            // loop through the sub directories of this directory.
            foreach ( $entry['dirs'] as $path => $dir ) {
                // bail if this directory is already loaded.
                if ( isset( $directory_list[ $path ] ) ) {
                    continue;
                }

                // use this directory.
                $next_directories[] = $path;
            }
        }

        // return the list of directories to load.
        return $next_directories;
    }

    /**
     * Build the tree.
     *
     * @param array<string,mixed> $directory_list The list of all directories.
     * @param string              $parent_url The parent URL.
     *
     * @return array<string,mixed>
     */
    private function build_tree( array $directory_list, string $parent_url = '' ) {
        // collect the tree.
        $tree = array();

        // ignore list.
        $ignore_dirs = array();

        // loop through all directories.
        foreach ( $directory_list as $url => $entry ) {
            // bail if given URL does not match with entry.
            if ( ! empty( $parent_url ) && $url !== $parent_url ) {
                continue;
            }

            // loop through the given dirs and add them to the tree.
            foreach ( $entry['dirs'] as $path => $subdir ) {
                // get the children.
                $children = $this->build_tree( $directory_list, $path );

                // add the children.
                if ( $children ) {
                    $entry['dirs'][ trailingslashit( $path ) ] = array_shift( $children );
                }

                if ( empty( $parent_url ) ) {
                    $ignore_dirs[] = $path;
                }
            }

            // add this element to the tree.
            if ( ! in_array( $url, $ignore_dirs, true ) ) {
                $tree[ trailingslashit( $url ) ] = $entry;
            }
        }

        // return resulting tree.
        return $tree;
    }
}
