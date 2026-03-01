<?php
/**
 * File to handle sodium-tasks.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Crypt;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use SodiumException;
use easyDirectoryListingForWordPress\Helper;
use easyDirectoryListingForWordPress\Crypt_Base;

/**
 * Object to handle crypt tasks with Sodium.
 */
class Sodium extends Crypt_Base {
    /**
     * Name of the method.
     *
     * @var string
     */
    protected string $name = 'sodium';

    /**
     * The constant used in wp-config.php for the hash.
     *
     * @var string
     */
    protected string $constant = 'EDLFW_SODIUM_HASH';

    /**
     * Coding-ID to use.
     *
     * @var int
     */
    private int $coding_id = SODIUM_BASE64_VARIANT_ORIGINAL;

    /**
     * Instance of this object.
     *
     * @var ?Sodium
     */
    private static ?Sodium $instance = null;

    /**
     * Return the instance of this Singleton object.
     */
    public static function get_instance(): Sodium {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initiate this method.
     *
     * @return void
     * @throws SodiumException On Exception through Sodium.
     * @throws Exception Could throw exception.
     */
    public function init(): void {
        if ( $this->is_hash_saved() ) {
            $this->set_hash( sodium_base642bin( EDLFW_SODIUM_HASH, $this->get_coding_id() ) ); // @phpstan-ignore constant.notFound
        }

        // bail if hash is set.
        if ( ! empty( $this->get_hash() ) ) {
            return;
        }

        // get hash from the old db entry.
        $this->set_hash( sodium_base642bin( get_option( EDLFW_SODIUM_DB_HASH, '' ), $this->get_coding_id() ) );

        // bail if the update is running, if cron or ajax is called or if this is not an admin-request.
        if ( defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' ) || ! is_admin() ) {
            return;
        }

        // if no hash is set, create one.
        if ( empty( $this->get_hash() ) ) {
            $hash = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
            $this->set_hash( $hash );
        }

        // get the wp-config.php path.
        $wp_config_php_path = Helper::get_wp_config_path();

        // bail if the path could not be loaded.
        if ( ! $wp_config_php_path ) {
            return;
        }

        // bail if wp-config.php is not writable.
        if ( ! Helper::is_writable( $wp_config_php_path ) ) {
            $this->create_mu_plugin();
            return;
        }

        // get WP Filesystem-handler.
        $wp_filesystem = Helper::get_wp_filesystem();

        // get the contents of the wp-config.php.
        $wp_config_php_content = $wp_filesystem->get_contents( $wp_config_php_path );

        // bail if the file has no contents.
        if ( ! $wp_config_php_content ) {
            return;
        }

        // remove previous value.
        $placeholder           = '## EASY DIRECTORY LISTING FOR WORDPRESS placeholder ##';
        $wp_config_php_content = preg_replace( '@^[\t ]*define\s*\(\s*["\']' . $this->get_constant() . '["\'].*$@miU', $placeholder, $wp_config_php_content );
        $wp_config_php_content = preg_replace( "@\n$placeholder@", '', (string) $wp_config_php_content );

        // add the constant.
        $define                = "define( '" . $this->get_constant() . "', '" . sodium_bin2base64( $this->get_hash(), $this->get_coding_id() ) . "' ); // Added by Easy Directory Listing for WordPress.\r\n";
        $wp_config_php_content = preg_replace( '@<\?php\s*@i', "<?php\n$define", (string) $wp_config_php_content, 1 );

        if ( ! is_string( $wp_config_php_content ) ) {
            return;
        }

        // save the changed wp-config.php.
        $wp_filesystem->put_contents( $wp_config_php_path, $wp_config_php_content );

        // delete the old option field.
        delete_option( EDLFW_SODIUM_DB_HASH );

        // run the constant for this process.
        $this->run_constant();
    }

    /**
     * Return whether this method is usable in this hosting.
     *
     * @return bool
     */
    public function is_usable(): bool {
        return function_exists( 'sodium_crypto_aead_aes256gcm_is_available' ) && sodium_crypto_aead_aes256gcm_is_available();
    }

    /**
     * Encrypt a given string.
     *
     * @param string $plain_text The plain string.
     *
     * @return string
     */
    public function encrypt( string $plain_text ): string {
        // bail if it is not usable.
        if ( ! $this->is_usable() ) {
            return '';
        }

        try {
            // generate a nonce.
            $nonce = random_bytes( SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES );

            // return encrypted text as base64.
            return sodium_bin2base64( $nonce . ':' . sodium_crypto_aead_aes256gcm_encrypt( $plain_text, '', $nonce, $this->get_hash() ), $this->get_coding_id() );
        } catch ( Exception $e ) {
            // return nothing.
            return '';
        }
    }

    /**
     * Decrypt a string.
     *
     * @param string $encrypted_text The encrypted string.
     *
     * @return string
     */
    public function decrypt( string $encrypted_text ): string {
        // bail if it is not usable.
        if ( ! $this->is_usable() ) {
            return '';
        }

        try {
            // split into the parts after converting from base64- to binary-string.
            $parts = explode( ':', sodium_base642bin( $encrypted_text, $this->get_coding_id() ) );

            // bail if an array is empty or does not have 2 entries.
            if ( count( $parts ) !== 2 ) {
                return '';
            }

            // return decrypted text.
            $decrypted = sodium_crypto_aead_aes256gcm_decrypt( $parts[1], '', $parts[0], $this->get_hash() );
            if ( ! is_string( $decrypted ) ) {
                return '';
            }
            return $decrypted;
        } catch ( Exception $e ) {
            // return nothing.
            return '';
        }
    }

    /**
     * Return the used coding ID.
     *
     * @return int
     */
    private function get_coding_id(): int {
        return $this->coding_id;
    }

    /**
     * Uninstall this method.
     *
     * @return void
     * @throws SodiumException On Exception through Sodium.
     */
    public function uninstall(): void {
        // initiate the method to get the actual hash.
        $this->init();

        // save the hash in db.
        update_option( EDLFW_SODIUM_DB_HASH, sodium_bin2base64( $this->get_hash(), $this->get_coding_id() ) );

        parent::uninstall();
    }

    /**
     * Return the secured hash value.
     *
     * @return string
     */
    protected function get_hash_value(): string {
        return sodium_bin2base64( $this->get_hash(), $this->get_coding_id() );
    }
}
