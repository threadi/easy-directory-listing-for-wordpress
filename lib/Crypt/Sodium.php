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
use easyDirectoryListingForWordPress\Crypt_Base;
use SodiumException;

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
	 * Constructor for this object.
	 *
	 * @throws SodiumException Possible exception.
	 * @throws Exception Possible exception.
	 */
	protected function __construct() {
		$this->set_hash( sodium_base642bin( get_option( EDLFW_SODIUM_HASH, '' ), $this->get_coding_id() ) );

		// initially generate a hash if it is empty.
		if ( empty( $this->get_hash() ) ) {
			$hash = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
			$this->set_hash( $hash );
			update_option( EDLFW_SODIUM_HASH, sodium_bin2base64( $this->get_hash(), $this->get_coding_id() ) );
		}

		parent::__construct();
	}

	/**
	 * Get encrypted text.
	 *
	 * @param string $plain_text The text to encrypt.
	 *
	 * @return string
	 */
	public function encrypt( string $plain_text ): string {
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
	 * Get decrypted text.
	 *
	 * @param string $encrypted_text Text to encrypt.
	 *
	 * @return string
	 */
	public function decrypt( string $encrypted_text ): string {
		try {
			// split into the parts after converting from base64- to binary-string.
			$parts = explode( ':', sodium_base642bin( $encrypted_text, $this->get_coding_id() ) );

			// bail if array is empty or does not have 2 entries.
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
}
