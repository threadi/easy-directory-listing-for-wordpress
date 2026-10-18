<?php
/**
 * File with a configurable listing object for the tests.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Fixtures;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use WP_Error;

/**
 * Listing object, which returns the configured listings, e.g. to simulate external sources.
 */
class Test_Listing extends Directory_Listing_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'edlfw_test_listing';

	/**
	 * List of listings per directory, which will be returned.
	 *
	 * @var array<string,array<int|string,mixed>>
	 */
	public array $listings = array();

	/**
	 * List of error texts, which will be added on each listing request.
	 *
	 * @var array<int,string>
	 */
	public array $error_texts = array();

	/**
	 * The permission to use this listing.
	 *
	 * @var string
	 */
	public string $permission = '';

	/**
	 * The result of the login.
	 *
	 * @var bool
	 */
	public bool $login_result = true;

	/**
	 * List of requested directories.
	 *
	 * @var array<int,string>
	 */
	public array $requested = array();

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array<int,Directory_Listing_Base> $list The list of listing objects.
	 *
	 * @return array<int,Directory_Listing_Base>
	 */
	public function register( array $list ): array {
		$list[] = $this;
		return $list;
	}

	/**
	 * Return the configured listing of the requested directory.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		$this->requested[] = $directory;

		foreach ( $this->error_texts as $text ) {
			$this->add_error( new WP_Error( 'edlfw_test', $text ) );
		}

		return $this->listings[ $directory ] ?? array();
	}

	/**
	 * Return the configured result of the login.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		if ( ! $this->login_result ) {
			$this->add_error( new WP_Error( 'edlfw_test', 'Login failed.' ) );
		}
		return $this->login_result;
	}

	/**
	 * Return the permission name to use this listing.
	 *
	 * @return string
	 */
	public function get_permission_name(): string {
		return empty( $this->permission ) ? parent::get_permission_name() : $this->permission;
	}
}
