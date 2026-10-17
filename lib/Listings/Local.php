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
use WP_Image_Editor;

/**
 * Object, which handle the local listing of directories.
 */
class Local extends Directory_Listing_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'local';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local
	 */
	private static ?Local $instance = null;

	/**
	 * The local WP Filesystem object.
	 *
	 * @var \WP_Filesystem_Base|null
	 */
	private ?\WP_Filesystem_Base $filesystem = null;

	/**
	 * Map of allowed file extensions to their mime types.
	 *
	 * @var array<string,string>|null
	 */
	private ?array $mime_types = null;

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
		$this->label = Init::get_instance()->get_translations()['services']['local']['label'];
		$this->title = Init::get_instance()->get_translations()['services']['local']['title'];
		add_filter( Init::get_instance()->get_prefix() . '_service_local_hide_file', array( $this, 'hide_files' ), 10, 2 );
	}

	/**
	 * Return the label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return Init::get_instance()->get_translations()['services']['local']['label'];
	}

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return Init::get_instance()->get_translations()['services']['local']['title'];
	}

	/**
	 * Return the directory to use.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// bail if directory is set on object.
		if ( ! empty( $this->directory ) ) {
			return $this->directory;
		}

		// return default path in hosting.
		return 'file://' . trailingslashit( ABSPATH );
	}

	/**
	 * Return the requested directory. The returning array contains:
	 *
	 * - list of all files in this directory
	 * - list of all directories in this directory
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// format the directory.
		$directory = trailingslashit( $directory );

		// collect the content of this directory.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// bail if another stream wrapper than "file://" is used (e.g., "ftp://", "phar://" or "http://").
		if ( str_contains( $directory, '://' ) && ! str_starts_with( $directory, 'file://' ) ) {
			// create error object.
			$error = new \WP_Error();
			$error->add( 'efml_service_' . $this->get_name(), __( 'The given directory is not a local directory.', 'easy-directory-listing-for-wordpress' ) );

			// add the error to the list for response.
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get WP Filesystem-handler.
		$wp_filesystem = $this->get_filesystem();

		// bail if wp_filesystem is not "direct".
		if ( ! $wp_filesystem instanceof \WP_Filesystem_Direct ) {
			// create error object.
			$error = new \WP_Error();
			$error->add( 'efml_service_' . $this->get_name(), __( 'Could not load necessary WordPress-component WP_Filesystem. Possible faulty configuration of FS_METHOD.', 'easy-directory-listing-for-wordpress' ) );

			// add the error to the list for response.
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// create the directory if it does not exist atm.
		if ( ! $wp_filesystem->exists( $upload_dir ) ) {
			$wp_filesystem->mkdir( $upload_dir );
		}

		// get the directory list.
		$dir_list = $wp_filesystem->dirlist( $directory );

		// bail if list could not be loaded.
		if ( ! is_array( $dir_list ) ) {
			return array();
		}

		// get the real path of the requested directory to detect symlink loops.
		$real_directory = realpath( str_replace( 'file://', '', $directory ) );

		/**
		 * Filter the max file size in bytes for images to generate a preview for.
		 *
		 * Larger images are not loaded in the image editor, as this needs a lot of memory and time.
		 *
		 * @since 4.3.0 Available since 4.3.0.
		 * @param int $max_size The max file size in bytes.
		 */
		$preview_max_size = absint( apply_filters( Init::get_instance()->get_prefix() . '_service_local_preview_max_filesize', 10 * MB_IN_BYTES ) );

		// loop through the directories and files.
		foreach ( $dir_list as $filename => $file ) {
			// get path.
			$path = $directory . $filename;

			$false = false;
			/**
			 * Filter whether given local file should be hidden.
			 *
			 * @since 2.1.0 Available since 2.1.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param string $path Absolute path to the given file.
			 * @param string $directory The requested directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( Init::get_instance()->get_prefix() . '_service_local_hide_file', $false, $path, $directory ) ) {
				continue;
			}

			// prepare an array for the entry.
			$entry = array(
				'title' => basename( (string) $filename ),
			);

			// if this is a directory, add it to the directory list.
			if ( 'd' === $file['type'] ) {
				// bail if this is a symlink to the actual directory or one of its parents, as this would result in an endless loop.
				$local_path = str_replace( 'file://', '', $path );
				if ( is_link( $local_path ) && is_string( $real_directory ) ) {
					$real_target = realpath( $local_path );
					if ( ! is_string( $real_target ) || str_starts_with( trailingslashit( $real_directory ), trailingslashit( $real_target ) ) ) {
						continue;
					}
				}

				$listing['dirs'][ trailingslashit( $path ) ] = $entry;
				continue;
			}

			// get content type of this file.
			$mime_type = $this->get_mime_type( (string) $filename );

			// bail if file is not allowed.
			if ( empty( $mime_type ) ) {
				continue;
			}

			// get size and modification time from the directory list (no further file system requests necessary).
			$filesize = (int) $file['size'];
			$mtime    = (int) ( $file['lastmodunix'] ?? 0 );

			// define the thumb.
			$thumbnail = '';

			// get the thumb via image editor object.
			if ( str_starts_with( $mime_type, 'image/' ) && Init::get_instance()->is_preview_enabled() && $filesize <= $preview_max_size ) {
				$thumbnail = $this->get_thumbnail( $path, $mime_type, $mtime, $upload_dir, $upload_url );
			}

			// add some more data to the file.
			$entry['file']          = $path;
			$entry['filesize']      = $filesize;
			$entry['mime-type']     = $mime_type;
			$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $mtime ) );
			$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type ) . '"></span>';
			$entry['preview']       = $thumbnail;

			// add the entry to the list.
			$listing['files'][] = $entry;
		}

		// return the listing.
		return $listing;
	}

	/**
	 * Return the local WP Filesystem object.
	 *
	 * The object is only loaded once per request, as WP_Filesystem() checks the filesystem method
	 * via a temporary test file on each call.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	private function get_filesystem(): ?\WP_Filesystem_Base {
		if ( null === $this->filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
			\WP_Filesystem();
			global $wp_filesystem;
			if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
				$this->filesystem = $wp_filesystem;
			}
		}
		return $this->filesystem;
	}

	/**
	 * Return the mime type of the given file name, if it is allowed in this WordPress.
	 *
	 * Same result as wp_check_filetype(), but the list of allowed types is only loaded once.
	 *
	 * @param string $filename The file name.
	 *
	 * @return string
	 */
	private function get_mime_type( string $filename ): string {
		// build the map of extensions to mime types.
		if ( null === $this->mime_types ) {
			$this->mime_types = array();
			foreach ( get_allowed_mime_types() as $extensions => $mime_type ) {
				foreach ( explode( '|', $extensions ) as $extension ) {
					if ( ! isset( $this->mime_types[ $extension ] ) ) {
						$this->mime_types[ $extension ] = $mime_type;
					}
				}
			}
		}

		// return the mime type for the extension of this file.
		return $this->mime_types[ strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ] ?? '';
	}

	/**
	 * Return the HTML code for the preview of the given image.
	 *
	 * The preview is generated only once per image and modification time.
	 *
	 * @param string $path The path of the image.
	 * @param string $mime_type The mime type from the file name.
	 * @param int    $mtime The modification time of the image.
	 * @param string $upload_dir The directory for the previews.
	 * @param string $upload_url The URL of the directory for the previews.
	 *
	 * @return string
	 */
	private function get_thumbnail( string $path, string $mime_type, int $mtime, string $upload_dir, string $upload_url ): string {
		// generate the path for WP-functions.
		$wp_compatible_path = str_replace( 'file://', '', $path );

		// get a unique file name for this image and its modification time, so images with the same name
		// in different directories do not overwrite each other and changed images get a new preview.
		$thumb_name = md5( $wp_compatible_path . '|' . $mtime ) . '.' . strtolower( pathinfo( $wp_compatible_path, PATHINFO_EXTENSION ) );

		// use the existing preview.
		if ( file_exists( $upload_dir . $thumb_name ) ) {
			return '<img src="' . esc_url( $upload_url . $thumb_name ) . '" alt="">';
		}

		// get the real image mime.
		$image_mime = wp_get_image_mime( $wp_compatible_path );

		// do nothing if the real mime does not start with "image/".
		if ( ! is_string( $image_mime ) || ! str_starts_with( $image_mime, 'image/' ) ) {
			return '';
		}

		// get image editor object of the file to get a thumb of it.
		$editor = wp_get_image_editor( $wp_compatible_path, array( 'mime_type' => $mime_type ) );

		// bail if object is not WP_Image_Editor.
		if ( ! $editor instanceof WP_Image_Editor ) {
			return '';
		}

		// set size for the preview.
		$editor->resize( 32, 32 );

		// save the thumbnail.
		$results = $editor->save( $upload_dir . $thumb_name );

		// bail on error.
		if ( is_wp_error( $results ) ) {
			return '';
		}

		// return the HTML code for the preview.
		return '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
	}

	/**
	 * Hide some directories and files for output in list.
	 *
	 * We initially hide the WordPress Core and content-directory.
	 *
	 * @param bool   $return_value The return value (true to hide).
	 * @param string $path The absolute path of the given file.
	 *
	 * @return bool
	 */
	public function hide_files( bool $return_value, string $path ): bool {
		// list of directories and files to hide.
		$hide_files = array(
			WP_CONTENT_DIR,
			ABSPATH . WPINC,
			ABSPATH . 'wp-admin',
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
			ABSPATH . 'xmlrpc.php',
		);

		// check the list.
		foreach ( $hide_files as $hide_file ) {
			// bail if the given path does not match the hidden file.
			if ( ! str_contains( $path, $hide_file ) ) {
				continue;
			}

			// return true as the path matches our hide list and should not be used.
			return true;
		}

		// return the resulting value.
		return $return_value;
	}

	/**
	 * Return the actions for each file.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		return $this->actions;
	}
}
