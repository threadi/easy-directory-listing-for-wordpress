<?php
/**
 * This file contains a helper object.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Filesystem_Base;
use WP_Filesystem_Direct;

/**
 * Initialize the helper.
 */
class Helper {
    /**
     * Format a given datetime with WP-settings and functions.
     *
     * @param string $date  A date as string.
     * @return string
     */
    public static function get_format_date_time( string $date ): string {
        $dt = get_date_from_gmt( $date );
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dt ) );
    }

    /**
     * Return the writable wp-config.php path.
     *
     * @return string
     */
    public static function get_wp_config_path(): string {
        $wp_config_php = 'wp-config';
        /**
         * Filter to change the filename of the used wp-config.php without its extension .php.
         *
         * @since 4.0.10 Available since 4.0.10.
         * @param string $wp_config_php The filename.
         */
        $wp_config_php = apply_filters( Init::get_instance()->get_prefix() . '_directory_listing_wp_config_name', $wp_config_php );

        // get the path for wp-config.php.
        $wp_config_php_path = ABSPATH . $wp_config_php . '.php';

        /**
         * Filter the path for the wp-config.php before we return it.
         *
         * @since 4.0.10 Available since 4.0.10.
         * @param string $wp_config_php_path The path.
         */
        return apply_filters( Init::get_instance()->get_prefix() . '_directory_listing_wp_config_path', $wp_config_php_path );
    }

    /**
     * Return whether a given file is writable.
     *
     * @param string $file The file with the absolute path.
     *
     * @return bool
     */
    public static function is_writable( string $file ): bool {
        return self::get_wp_filesystem()->is_writable( $file );
    }

    /**
     * Return the WP Filesystem object.
     *
     * @param bool $local Mark with "true" to get the local filesystem object.
     *
     * @return WP_Filesystem_Base
     */
    public static function get_wp_filesystem( bool $local = false ): WP_Filesystem_Base {
        // get WP Filesystem-handler for local files if requested.
        if ( $local ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

            return new WP_Filesystem_Direct( false );
        }

        // get global WP Filesystem handler.
        require_once ABSPATH . '/wp-admin/includes/file.php';
        \WP_Filesystem();
        global $wp_filesystem;

        // bail if wp_filesystem is not of "WP_Filesystem_Base".
        if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            return new WP_Filesystem_Direct( false );
        }

        // return the local object on any error.
        if ( $wp_filesystem->errors->has_errors() ) {
            // embed the local directory object.
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

            return new WP_Filesystem_Direct( false );
        }

        // return the requested filesystem object.
        return $wp_filesystem;
    }
}
