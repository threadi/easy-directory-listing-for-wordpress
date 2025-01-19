<?php
/**
 * File to handle the local listing of directories.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Listings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Helper;
use easyDirectoryListingForWordPress\Init;
use WP_Image_Editor_Imagick;

/**
 * Object which handle the local listing of directories.
 */
class Local extends Directory_Listing_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'local';

	/**
	 * List of actions for each file.
	 *
	 * @var array
	 */
	private array $actions = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local
	 */
	private static ?Local $instance = null;

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
	 * @return Local
	 */
	public static function get_instance(): Local {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->label = __( 'Local server directory' );
		$this->title = __( 'Choose file from local server directory' );
		add_filter( 'eml_service_local_hide_file', array( $this, 'hide_files' ), 10, 2 );
	}

	/**
	 * Return the directory to use.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return trailingslashit( ABSPATH );
	}

	/**
	 * Get directory recursively.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array
	 */
	public function get_directory_listing( string $directory ): array {
		// format the directory.
		$directory = trailingslashit( $directory );

		// collect the content of this directory.
		$listing = array();

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// create directory if it does not exist atm.
		if ( ! $wp_filesystem->exists( $upload_dir ) ) {
			$wp_filesystem->mkdir( $upload_dir );
		}

		// loop through the directories and files.
		foreach ( glob( $directory . '*' ) as $filename ) {
			$false = false;
			/**
			 * Filter whether given local file should be hidden.
			 *
			 * @since 2.1.0 Available since 2.1.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param string $filename Absolute path to the given file.
			 * @param string $directory The requested directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'eml_service_local_hide_file', $false, $filename, $directory ) ) {
				continue;
			}

			// get the type.
			$type = 'file';
			if ( is_dir( $filename ) ) {
				$type = 'dir';
			}

			// create object for entry.
			$entry = array(
				$type   => $filename,
				'title' => basename( $filename ),
			);

			// if this is a directory, check it recursively.
			if ( is_dir( $filename ) ) {
				// get sub directories.
				$subs           = $this->get_directory_listing( $filename );
				$entry['sub']   = $subs;
				$entry['count'] = count( $subs );
			} else {
				// get content type of this file.
				$mime_type = wp_check_filetype( $filename );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// get image editor object of the file to get a thumb of it.
				$editor = wp_get_image_editor( $filename );

				// define the thumb.
				$thumbnail = '';

				// get the thumb via image editor object.
				if ( Init::get_instance()->is_preview_enabled() && $editor instanceof WP_Image_Editor_Imagick ) {
					// set size for the preview.
					$editor->resize( 32, 32 );

					// save the thumb.
					$results = $editor->save( $upload_dir . '/' . basename( $filename ) );

					// add thumb to output if it does not result in an error.
					if ( ! is_wp_error( $results ) ) {
						$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
					}
				}

				// add some more data to the file.
				$entry['filesize']      = filesize( $filename );
				$entry['mime-type']     = $mime_type['type'];
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $wp_filesystem->mtime( $filename ) ) );
				$entry['icon']          = '<span class="dashicons dashicons-media-default"></span>';
				$entry['preview']       = $thumbnail;
			}

			// add the entry to the list.
			$listing[] = $entry;
		}

		// return the listing.
		return $listing;
	}

	/**
	 * Hide some directories and files for output in list.
	 *
	 * We initially hide the WordPress Core and content-directory.
	 *
	 * @param bool   $return_value The return value (true to hide).
	 * @param string $filename The given file.
	 *
	 * @return bool
	 */
	public function hide_files( bool $return_value, string $filename ): bool {
		// list of directories and files to hide.
		$hide_files = array(
			WP_CONTENT_DIR,
			ABSPATH . WPINC,
			ABSPATH . 'wp-admin/',
			ABSPATH . 'wp-blog-header.php',
			ABSPATH . 'wp-comments-post.php',
			ABSPATH . 'wp-config-sample.php',
			ABSPATH . 'wp-config.php',
			ABSPATH . 'wp-cron.php',
			ABSPATH . 'wp-links-opml.php',
			ABSPATH . 'wp-activate.php',
			ABSPATH . 'wp-load.php',
			ABSPATH . 'wp-login.php',
			ABSPATH . 'wp-mail.php',
			ABSPATH . 'wp-settings.php',
			ABSPATH . 'wp-signup.php',
			ABSPATH . 'wp-trackback.php',
			ABSPATH . 'wplogo.png',
			ABSPATH . 'xmlrpc.php',
		);

		// check the list.
		foreach ( $hide_files as $hide_file ) {
			// bail if the given path does not match the hidden file.
			if ( ! str_contains( $hide_file, $filename ) ) {
				continue;
			}

			// path is matching, so we return true.
			$return_value = true;
		}

		// return the resulting value.
		return $return_value;
	}

	/**
	 * Return the actions for each file.
	 *
	 * @return array
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Set actions.
	 *
	 * @param array $actions List of actions for each file.
	 *
	 * @return void
	 */
	public function set_actions( array $actions ): void {
		$this->actions = $actions;
	}
}
