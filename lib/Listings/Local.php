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
        $this->label = Init::get_instance()->get_translations()['services']['local']['label'];
        $this->title = Init::get_instance()->get_translations()['services']['local']['title'];
        add_filter( Init::get_instance()->get_prefix() . '_service_local_hide_file', array( $this, 'hide_files' ), 10, 2 );
    }

    /**
     * Return the directory to use.
     *
     * @return string
     */
    public function get_directory(): string {
        // bail if directory is set on object.
        if( ! empty( $this->directory ) ) {
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

        // get WP Filesystem-handler.
        require_once ABSPATH . '/wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
        \WP_Filesystem();
        global $wp_filesystem;

        // bail if wp_filesystem is not set.
        if( is_null( $wp_filesystem ) ) {
            // create error object.
            $error = new \WP_Error();
            $error->add( 'efml_service_' . $this->get_name(), __( 'Could not load necessary WordPress-component WP_Filesystem. Possible faulty configuration of FS_METHOD.', 'easy-directory-listing-for-wordpress' ) );

            // add the error to the list for response.
            $this->add_error( $error );

            // do nothing more.
            return array();
        }

        // bail if wp_filesystem is not "direct".
        if( ! $wp_filesystem instanceof \WP_Filesystem_Direct ) {
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

        // create directory if it does not exist atm.
        if ( ! $wp_filesystem->exists( $upload_dir ) ) {
            $wp_filesystem->mkdir( $upload_dir );
        }

        // loop through the directories and files.
        foreach ( $wp_filesystem->dirlist( $directory ) as $filename => $file ) {
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

            // create array for entry.
            $entry = array(
                'title' => basename( $filename ),
            );

            // if this is a directory, add it to the directory list.
            if ( is_dir( $path ) ) {
                $listing['dirs'][ trailingslashit( $path ) ] = $entry;
            } else {
                // get content type of this file.
                $mime_type = wp_check_filetype( $path );

                // bail if file is not allowed.
                if ( empty( $mime_type['type'] ) ) {
                    continue;
                }

                // define the thumb.
                $thumbnail = '';

                // get the thumb via image editor object.
                if ( str_contains( $mime_type['type'], 'image/' ) && Init::get_instance()->is_preview_enabled() ) {
                    // get the real image mime.
                    $image_mime = wp_get_image_mime( $path );

                    // do nothing if the real mime does not start with "image/".
                    if( str_contains( $image_mime, 'image/' ) ) {
                        // generate path for WP-functions.
                        $wp_compatible_path = str_replace( 'file://', '', $path );

                        // get image editor object of the file to get a thumb of it.
                        $editor = wp_get_image_editor( $wp_compatible_path, $mime_type );

                        // check if object is WP_Image_Editor.
                        if ( $editor instanceof WP_Image_Editor ) {
                            // set size for the preview.
                            $editor->resize( 32, 32 );

                            // save the thumbnail.
                            $results = $editor->save( $upload_dir . '/' . basename( $path ) );

                            // add thumb to output if it does not result in an error.
                            if ( ! is_wp_error( $results ) ) {
                                $thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
                            }
                        }
                    }
                }

                // add some more data to the file.
                $entry['file']          = $path;
                $entry['filesize']      = filesize( $path );
                $entry['mime-type']     = $mime_type['type'];
                $entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $wp_filesystem->mtime( $path ) ) );
                $entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
                $entry['preview']       = $thumbnail;

                // add the entry to the list.
                $listing['files'][] = $entry;
            }
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
