<?php
/**
 * File to handle the supported directory listing objects in WordPress.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Listings\Local;

/**
 * Object to handle the supported directory listing objects.
 */
class Directory_Listings {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Directory_Listings
	 */
	private static ?Directory_Listings $instance = null;

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
	 * @return Directory_Listings
	 */
	public static function get_instance(): Directory_Listings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the possible listing objects.
		foreach ( $this->get_directory_listings_objects() as $obj ) {
			$obj->init();
		}

        // use hooks.
        add_action( 'admin_init', array( $this, 'cleanup' ) );
	}

	/**
	 * Return the list of supported directory listing objects.
	 *
	 * @return array<int,Directory_Listing_Base>
	 */
	public function get_directory_listings_objects(): array {
		$list = array(
			Local::get_instance(),
		);

		/**
		 * Filter for supported directory listing objects.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array<int,Directory_Listing_Base> $list List of supported directory listing objects.
		 */
		return apply_filters( Init::get_instance()->get_prefix() . '_directory_listing_objects', $list );
	}

	/**
	 * Return a directory listing object by its name.
	 *
	 * @param string $name Name of the object.
	 *
	 * @return false|Directory_Listing_Base
	 */
	public function get_directory_listing_object_by_name( string $name ): false|Directory_Listing_Base {
		// get the possible listing objects.
		foreach ( $this->get_directory_listings_objects() as $obj ) {
			// bail if name does not match.
			if ( $name !== $obj->get_name() ) {
				continue;
			}

			// return this object as result.
			return $obj;
		}

		// return false if no object could be found.
		return false;
	}

	/**
	 * Return the archive URL for our taxonomy.
	 *
	 * @return string
	 */
	public function get_directory_archive_url(): string {
		return add_query_arg(
			array(
				'taxonomy' => Taxonomy::get_instance()->get_name(),
			),
			get_admin_url() . 'edit-tags.php'
		);
	}

    /**
     * Cleanup our own directory for files used to preview images in listings.
     *
     * @return void
     */
    public function cleanup(): void {
        // get upload directory.
        $upload_dir_data = wp_get_upload_dir();
        $upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';

        // get WP Filesystem-handler.
        require_once ABSPATH . '/wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
        \WP_Filesystem();
        global $wp_filesystem;

        // bail if directory does not exist.
        if( ! $wp_filesystem->exists( $upload_dir ) ) {
            return;
        }

        // get all files from the directory.
        $files = glob( $upload_dir . '/*.*' );

        // loop through them and delete the files which are older than 1 day.
        $max_age = time() - DAY_IN_SECONDS;
        foreach( $files as $file ) {
            // bail if file is younger than 1 day.
            if( filemtime( $file ) > $max_age ) {
                continue;
            }

            // delete the file.
            $wp_filesystem->delete( $file );
        }
    }
}
