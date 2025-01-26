<?php
/**
 * File to handle directory listing methods as base-object.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Object to handle directory listing methods as base-object.
 */
class Directory_Listing_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = '';

	/**
	 * The public title.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * Marker if login (with login and password) is required.
	 *
	 * @var bool
	 */
	protected bool $requires_login = false;

	/**
	 * Marker if simple API is required (with API Key).
	 *
	 * @var bool
	 */
	protected bool $requires_simple_api = false;

	/**
	 * List of global actions for this listing object.
	 *
	 * @var array
	 */
	private array $global_actions = array();

	/**
	 * The login.
	 *
	 * @var string
	 */
	protected string $login = '';

	/**
	 * The password.
	 *
	 * @var string
	 */
	protected string $password = '';

	/**
	 * The API Key.
	 *
	 * @var string
	 */
	protected string $api_key = '';

	/**
	 * List of errors.
	 *
	 * @var array
	 */
	protected array $errors = array();

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Return the object name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the directory to use.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return '';
	}

	/**
	 * Return whether this directory is login protected.
	 *
	 * @return bool
	 */
	public function is_login_required(): bool {
		return $this->requires_login;
	}

	/**
	 * Return whether this directory is protected with simple API key.
	 *
	 * @return bool
	 */
	public function is_simple_api_required(): bool {
		return $this->requires_simple_api;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array
	 */
	public function get_directory_listing( string $directory ): array {
		if ( empty( $directory ) ) {
			return array();
		}
		return array();
	}

	/**
	 * Return the actions.
	 *
	 * @return array
	 */
	public function get_actions(): array {
		return array();
	}

	/**
	 * Check if login with given credentials is valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if directory is not set.
		if ( empty( $directory ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Return the used login.
	 *
	 * @return string
	 */
	protected function get_login(): string {
		return $this->login;
	}

	/**
	 * Set the login.
	 *
	 * @param string $login The login.
	 *
	 * @return void
	 */
	public function set_login( string $login ): void {
		$this->login = $login;
	}

	/**
	 * Return the used passwort.
	 *
	 * @return string
	 */
	protected function get_password(): string {
		return $this->password;
	}

	/**
	 * Set the login.
	 *
	 * @param string $password The login.
	 *
	 * @return void
	 */
	public function set_password( string $password ): void {
		$this->password = $password;
	}

	/**
	 * Return the label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Return translatable labels.
	 *
	 * @return array
	 */
	public function get_i18n(): array {
		return array(
			'is_loading'       => __( 'Please wait, list is loading.' ),
			'could_not_load'   => __( 'Directory could not be loaded.' ),
			'reload'           => __( 'Reload' ),
			'import_directory' => __( 'Import active directory' ),
			'actions'          => __( 'Actions' ),
			'filename'         => __( 'Filename' ),
			'filesize'         => __( 'Size', ),
			'date'             => __( 'Date' ),
		);
	}

	/**
	 * Return config for display of listing in backend.
	 *
	 * @return array
	 */
	public function get_config(): array {
		return array(
			'directory'                => $this->get_directory(),
			'listing_base_object_name' => $this->get_name(),
			'requires_login'           => $this->is_login_required(),
			'requires_simple_api'      => $this->is_simple_api_required(),
			'i18n'                     => $this->get_i18n(),
			'nonce'                    => wp_create_nonce( $this->get_nonce_name() ),
			'actions'                  => $this->get_actions(),
			'global_actions'           => $this->get_global_actions(),
		);
	}

	/**
	 * Return nonce name.
	 *
	 * @return string
	 */
	public function get_nonce_name(): string {
		return 'easy-directory-listing-for-wordpress';
	}

	/**
	 * Return global actions.
	 *
	 * @return array
	 */
	protected function get_global_actions(): array {
		if ( empty( $this->global_actions ) ) {
			$this->global_actions = array(
				array(
					'action' => 'setActualDirectory(false);directoryListing(false);',
					'label'  => __( 'Reload' ),
				),
			);
		}
		return $this->global_actions;
	}

	/**
	 * Add single global action.
	 *
	 * @param array $global_action Single global action to add.
	 *
	 * @return void
	 */
	public function add_global_action( array $global_action ): void {
		$this->global_actions = array_merge( $this->get_global_actions(), $global_action );
	}

	/**
	 * Return the API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Set the API key to use.
	 *
	 * @param string $api_key The API key to use.
	 *
	 * @return void
	 */
	public function set_api_key( string $api_key ): void {
		$this->api_key = $api_key;
	}

	/**
	 * Add error to the list.
	 *
	 * @param WP_Error $error The error as WP_Error.
	 *
	 * @return void
	 */
	public function add_error( WP_Error $error ): void {
		$this->errors[] = $error;
	}

	/**
	 * Return the list of errors.
	 *
	 * @return array
	 */
	public function get_errors(): array {
		return $this->errors;
	}
}
