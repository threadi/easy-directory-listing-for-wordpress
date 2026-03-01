<?php
/**
 * File to handle crypt methods as base-object.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle crypt methods as base-object.
 */
class Crypt_Base {
    /**
     * Name of the method.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The hash for encryption.
     *
     * @var string
     */
    protected string $hash = '';

    /**
     * The constant used in wp-config.php for the hash.
     *
     * @var string
     */
    protected string $constant = '';

    /**
     * Constructor for this object.
     */
    protected function __construct() {}

    /**
     * Prevent cloning of this object.
     *
     * @return void
     */
    protected function __clone() {}

    /**
     * Initialize this crypt method.
     *
     * @return void
     */
    public function init(): void {}

    /**
     * Return whether this method is usable in this hosting.
     *
     * @return bool
     */
    public function is_usable(): bool {
        return false;
    }

    /**
     * Return name of the method.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Encrypt a given string.
     *
     * @param string $plain_text The plain string.
     *
     * @return string
     */
    public function encrypt( string $plain_text ): string {
        if ( empty( $plain_text ) ) {
            return $plain_text;
        }
        return '';
    }

    /**
     * Decrypt a given string.
     *
     * @param string $encrypted_text The encrypted string.
     *
     * @return string
     */
    public function decrypt( string $encrypted_text ): string {
        if ( empty( $encrypted_text ) ) {
            return $encrypted_text;
        }
        return '';
    }

    /**
     * Return hash for encryption.
     *
     * @return string
     */
    public function get_hash(): string {
        return $this->hash;
    }

    /**
     * Return the secured hash value.
     *
     * @return string
     */
    protected function get_hash_value(): string {
        return $this->hash;
    }

    /**
     * Set hash for encryption.
     *
     * @param string $hash The hash.
     *
     * @return void
     */
    protected function set_hash( string $hash ): void {
        $this->hash = $hash;
    }

    /**
     * Return whether the hash is saved in wp-config.php.
     *
     * @return bool
     */
    public function is_hash_saved(): bool {
        return defined( $this->get_constant() );
    }

    /**
     * Run the constant.
     *
     * @return void
     */
    protected function run_constant(): void {
        if ( $this->is_hash_saved() ) {
            return;
        }
        define( $this->get_constant(), $this->get_hash() );
    }

    /**
     * Return the used constant in wp-config.php.
     *
     * @return string
     */
    protected function get_constant(): string {
        return $this->constant;
    }

    /**
     * Return the header for the MU-plugin.
     *
     * @return string
     */
    private function get_php_header(): string {
        return '
/**
 * Plugin Name:       Easy Directory for WordPress Hash
 * Description:       Holds the hash value to use encryption.
 * Requires at least: 4.9.24
 * Requires PHP:      8.1
 * Version:           1.0.0
 * Author:            laOlaWeb
 * Author URI:        https://laolaweb.com
 * Text Domain:       easy-directory-listing-for-wordpress-hash
 *
 * @package easy-directory-listing-for-wordpress-hash
 */';
    }

    /**
     * Return the mu plugin filename.
     *
     * @return string
     */
    private function get_mu_plugin_filename(): string {
        return 'easy-directory-listing-for-wordpress-hash.php';
    }

    /**
     * Create the MU-plugin which is used as fallback if wp-config.php could not be changed.
     *
     * @return void
     */
    protected function create_mu_plugin(): void {
        // bail if WPMU_PLUGIN_DIR is not set.
        if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
            return;
        }

        // get WP Filesystem-handler.
        $wp_filesystem = Helper::get_wp_filesystem();

        // create a custom must-use-plugin instead.
        $file_content = '<?php ' . $this->get_php_header() . "\ndefine( '" . $this->get_constant() . "', '" . $this->get_hash_value() . "' ); // Added by Easy Directory Listing for WordPress.\r\n";

        // create mu-plugin directory if it is missing.
        if ( ! $wp_filesystem->exists( WPMU_PLUGIN_DIR ) ) {
            $wp_filesystem->mkdir( WPMU_PLUGIN_DIR );
        }

        // define path.
        $file_path = WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->get_mu_plugin_filename();

        // save the file.
        if ( ! $wp_filesystem->put_contents( $file_path, $file_content ) ) {
            return;
        }

        // run the constant for this process.
        $this->run_constant();
    }

    /**
     * Delete our own mu-plugin.
     *
     * @return void
     */
    protected function delete_mu_plugin(): void {
        // bail if WPMU_PLUGIN_DIR is not set.
        if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
            return;
        }

        // get WP Filesystem-handler.
        $wp_filesystem = Helper::get_wp_filesystem();

        // bail if mu directory does not exist.
        if ( ! $wp_filesystem->exists( WPMU_PLUGIN_DIR ) ) {
            return;
        }

        // define path.
        $file_path = WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->get_mu_plugin_filename();

        // delete the file.
        $wp_filesystem->delete( $file_path );
    }

    /**
     * Uninstall this method.
     *
     * @return void
     */
    public function uninstall(): void {
        // get the wp-config.php path.
        $wp_config_php_path = Helper::get_wp_config_path();

        // bail if wp-config.php is not writable.
        if ( ! Helper::is_writable( $wp_config_php_path ) ) {
            // remove mu-plugin.
            $this->delete_mu_plugin();
            return;
        }

        // get WP Filesystem-handler.
        $wp_filesystem = Helper::get_wp_filesystem();

        // get the contents of the wp-config.php.
        $wp_config_php_content = $wp_filesystem->get_contents( $wp_config_php_path );

        // bail if file has no contents.
        if ( ! $wp_config_php_content ) {
            return;
        }

        // remove the value.
        $wp_config_php_content = preg_replace( '@^[\t ]*define\s*\(\s*["\']' . $this->get_constant() . '["\'].*$@miU', '', $wp_config_php_content );

        if ( ! is_string( $wp_config_php_content ) ) {
            return;
        }

        // save the changed wp-config.php.
        $wp_filesystem->put_contents( $wp_config_php_path, $wp_config_php_content );
    }
}
