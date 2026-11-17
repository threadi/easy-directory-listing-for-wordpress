<?php
/**
 * File with the handler for any crypt tasks in this library.
 * We use the composer package "Crypt for WordPress" as help for these tasks.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Closure;
use CryptForWordPress\Method_Base;

/**
 * Object to handle any crypt tasks in this plugin.
 *
 * A value could be bound to a context, e.g. the name of the field it is saved in. It can only be
 * decrypted with the very same context then. Values encrypted before the context has been used
 * (without context) stay readable: decrypt() tries them without context, if they could not be
 * decrypted with it. Only if this fails too, an error is reported - once, with the context.
 */
class Crypt {
	/**
	 * The used crypt object.
	 *
	 * @var \CryptForWordPress\Crypt|null
	 */
	private ?\CryptForWordPress\Crypt $crypt_obj = null;

	/**
	 * Instance of this object.
	 *
	 * @var ?Crypt
	 */
	private static ?Crypt $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Crypt {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the crypt object with its configuration for this plugin.
	 *
	 * @return \CryptForWordPress\Crypt
	 */
	private function get_crypt_obj(): \CryptForWordPress\Crypt {
		if ( null === $this->crypt_obj ) {
			// configure the crypt object.
			$this->crypt_obj = new \CryptForWordPress\Crypt( Init::get_instance()->get_plugin_path() );
			$this->crypt_obj->set_config(
				array(
					'openssl' => array(
						'hash_type'        => 'hash',
						'hash_algorithm'   => 'sha256',
						'cipher_algorithm' => 'AES-128-CBC',
					),
					'sodium'  => array(
						'hash_type' => 'sodium_crypto_secretbox_keygen',
					),
				)
			);
		}

		// return the crypt object.
		return $this->crypt_obj;
	}

	/**
	 * Encrypt a given string.
	 *
	 * @param string $plain_text The plain string.
	 * @param string $context    The context, e.g. the name of the field (optional). Never use anything secret or
	 *                           anything, which changes for this value (like the ID of a post).
	 *
	 * @return string
	 */
	public function encrypt( string $plain_text, string $context = '' ): string {
		return $this->get_crypt_obj()->encrypt( $plain_text, $context );
	}

	/**
	 * Decrypt a given string.
	 *
	 * With a context, the value is decrypted with this context first. If this fails, it is decrypted
	 * without context, as values saved before the context has been used are encrypted without it. The
	 * first attempt reports no error. If both fail, the errors are reported once, with the context in
	 * their data. So the error names the affected value.
	 *
	 * @param string $encrypted_string The encrypted string.
	 * @param string $context          The context, the value has been encrypted with (optional).
	 *
	 * @return string
	 */
	public function decrypt( string $encrypted_string, string $context = '' ): string {
		// without context, decrypt it as before.
		if ( '' === $context ) {
			return $this->get_crypt_obj()->decrypt( $encrypted_string );
		}

		// bail if there is nothing to decrypt.
		if ( '' === $encrypted_string ) {
			return '';
		}

		// initialize the method first: errors of its initialization (e.g. a missing key) are not
		// errors of this value and are reported as usual.
		$this->get_crypt_obj()->get_method();

		// decrypt it with its context.
		$errors_with_context = array();
		$plain_text          = $this->decrypt_quietly( $encrypted_string, $context, $errors_with_context );
		if ( '' !== $plain_text ) {
			// report the warnings of this attempt (e.g. a value, which has never been protected).
			$this->report_errors( $errors_with_context, $context );

			// return the decrypted value.
			return $plain_text;
		}

		// decrypt it without context (the value has been encrypted before the context has been used).
		$errors_without_context = array();
		$plain_text             = $this->decrypt_quietly( $encrypted_string, '', $errors_without_context );
		if ( '' !== $plain_text ) {
			// report the warnings of this attempt.
			$this->report_errors( $errors_without_context, $context );

			// return the decrypted value.
			return $plain_text;
		}

		// report the errors once, with the context.
		$this->report_errors( $this->get_errors_to_report( $errors_with_context, $errors_without_context ), $context );

		// return an empty string, as the value could not be decrypted.
		return '';
	}

	/**
	 * Decrypt a value without reporting its errors to the listeners of the error hook. The errors are
	 * collected in the given list instead.
	 *
	 * @param string                                                         $encrypted_string The encrypted string.
	 * @param string                                                         $context          The context.
	 * @param array<int,array{code:string,message:string,data:array<mixed>}> $errors           The list of errors.
	 *
	 * @return string
	 */
	private function decrypt_quietly( string $encrypted_string, string $context, array &$errors ): string {
		global $wp_filter;

		// get the name of the hook, which the crypt object runs for each error.
		$hook = $this->get_crypt_obj()->get_slug() . '_crypt_error';

		// remove its listeners for this attempt.
		$listeners = $wp_filter[ $hook ] ?? null;
		unset( $wp_filter[ $hook ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// collect the errors of this attempt instead.
		$collector = static function ( $code, $message, $data ) use ( &$errors ): void {
			$errors[] = array(
				'code'    => (string) $code,
				'message' => (string) $message,
				'data'    => is_array( $data ) ? $data : array(),
			);
		};
		add_action( $hook, $collector, 10, 3 );

		try {
			return $this->get_crypt_obj()->decrypt( $encrypted_string, $context );
		} finally {
			// restore the listeners.
			remove_action( $hook, $collector );
			if ( null !== $listeners ) {
				$wp_filter[ $hook ] = $listeners; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			} else {
				unset( $wp_filter[ $hook ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Return the errors to report for a value, which could neither be decrypted with nor without its context.
	 *
	 * The errors of the attempt with context are the reason, unless the value is in a format of an older
	 * version of the crypt package, which could not be bound to a context. Then the attempt without context
	 * names the reason.
	 *
	 * @param array<int,array{code:string,message:string,data:array<mixed>}> $errors_with_context    Errors with context.
	 * @param array<int,array{code:string,message:string,data:array<mixed>}> $errors_without_context Errors without context.
	 *
	 * @return array<int,array{code:string,message:string,data:array<mixed>}>
	 */
	private function get_errors_to_report( array $errors_with_context, array $errors_without_context ): array {
		// remove the errors, which only say that the format does not support a context.
		$errors = array_values(
			array_filter(
				$errors_with_context,
				static function ( array $error ): bool {
					return ! str_ends_with( $error['code'], '_context_unsupported' );
				}
			)
		);

		// use the errors without context, if no other error is left.
		if ( empty( $errors ) ) {
			return $errors_without_context;
		}

		// return the errors.
		return $errors;
	}

	/**
	 * Report the given errors with the given context.
	 *
	 * @param array<int,array{code:string,message:string,data:array<mixed>}> $errors  The errors.
	 * @param string                                                         $context The context.
	 *
	 * @return void
	 */
	private function report_errors( array $errors, string $context ): void {
		foreach ( $errors as $error ) {
			// set the context, so the error names the affected value.
			$data            = $error['data'];
			$data['context'] = $context;

			// report it.
			$this->get_crypt_obj()->add_error( $error['code'], $error['message'], $data );
		}
	}

	/**
	 * Return the callback to encrypt the value of a setting, bound to a context.
	 *
	 * Empty values are saved as empty string (a value like "0" is a valid value).
	 *
	 * @param string $context The context, e.g. the name of the setting.
	 *
	 * @return Closure
	 */
	public function get_save_callback( string $context ): Closure {
		return function ( $value ) use ( $context ): string {
			// bail if value is not a string or empty.
			if ( ! is_string( $value ) || '' === $value ) {
				return '';
			}

			// return the encrypted value.
			return $this->encrypt( $value, $context );
		};
	}

	/**
	 * Return the callback to decrypt the value of a setting, bound to a context.
	 *
	 * Values saved without context (before the context has been used) stay readable, see decrypt().
	 *
	 * @param string $context The context, e.g. the name of the setting.
	 *
	 * @return Closure
	 */
	public function get_read_callback( string $context ): Closure {
		return function ( $value ) use ( $context ): string {
			// bail if value is not a string or empty.
			if ( ! is_string( $value ) || '' === $value ) {
				return '';
			}

			// return the decrypted value.
			return $this->decrypt( $value, $context );
		};
	}

	/**
	 * Return the used method.
	 *
	 * @return Method_Base|false
	 */
	public function get_method(): Method_Base|false {
		return $this->get_crypt_obj()->get_method();
	}
}
