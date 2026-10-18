<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Rest.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit;

use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;
use easyDirectoryListingForWordPress\Tests\Fixtures\Test_Listing;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Rest.
 */
class Rest extends easyDirectoryListingTests {
	/**
	 * The configurable listing object.
	 *
	 * @var Test_Listing
	 */
	private Test_Listing $test_listing;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// add the configurable listing object.
		$this->test_listing = new Test_Listing();
		add_filter( self::hook( 'directory_listing_objects' ), array( $this->test_listing, 'register' ) );

		$this->do_login();
	}

	/**
	 * Return the parameters for a request with the configurable listing object.
	 *
	 * @param string $directory The directory to load.
	 *
	 * @return array<string,mixed>
	 */
	private function get_test_listing_params( string $directory ): array {
		return array(
			'listing_base_object_name' => 'edlfw_test_listing',
			'directory'                => $directory,
		);
	}

	/**
	 * Return a directory entry like the listing objects return it.
	 *
	 * @param string             $title The title.
	 * @param array<int,string>  $dirs List of paths of sub-directories.
	 * @param array<int,mixed>   $files List of files.
	 *
	 * @return array<string,mixed>
	 */
	private function entry( string $title, array $dirs = array(), array $files = array() ): array {
		$sub_directories = array();
		foreach ( $dirs as $path ) {
			$sub_directories[ $path ] = array( 'title' => basename( $path ) );
		}
		return array(
			'title' => $title,
			'files' => $files,
			'dirs'  => $sub_directories,
		);
	}

	/**
	 * Test if the endpoint is registered.
	 *
	 * @return void
	 */
	public function test_endpoint_is_registered(): void {
		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	/**
	 * Test if the endpoint is not usable without login.
	 *
	 * @return void
	 */
	public function test_endpoint_requires_login(): void {
		$this->do_logout();
		$response = $this->request( array() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test if the endpoint is not usable for users without the capability.
	 *
	 * @return void
	 */
	public function test_endpoint_requires_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = $this->request( array() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test if a missing nonce results in an error.
	 *
	 * @return void
	 */
	public function test_missing_nonce(): void {
		$response = $this->request( array( 'nonce' => '' ) );
		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 'edlfw_invalid_nonce', $response->get_data()['code'] );
	}

	/**
	 * Test if a wrong nonce results in an error and not in an empty response (which would show the loading hint forever).
	 *
	 * @return void
	 */
	public function test_wrong_nonce(): void {
		$response = $this->request( array( 'nonce' => 'wrong' ) );
		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 'edlfw_invalid_nonce', $response->get_data()['code'] );
		$this->assertNotEmpty( $response->get_data()['message'] );
	}

	/**
	 * Test if the nonce is checked before a saved term is loaded.
	 *
	 * @return void
	 */
	public function test_nonce_is_checked_before_term_is_loaded(): void {
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'edlfw_test_listing', '/root/', array() );

		// requesting the entry with a wrong nonce must not touch the term.
		$loaded = false;
		add_filter(
			'get_term',
			static function ( $term ) use ( &$loaded ) {
				$loaded = true;
				return $term;
			}
		);
		$response = $this->request( array( 'nonce' => 'wrong', 'term' => $term_id ) );

		$this->assertEquals( 403, $response->get_status() );
		$this->assertFalse( $loaded );
	}

	/**
	 * Test if an unknown listing object results in an empty response.
	 *
	 * @return void
	 */
	public function test_unknown_listing_object(): void {
		$response = $this->request( array( 'listing_base_object_name' => 'unknown' ) );
		$this->assertEquals( array(), $response->get_data() );
	}

	/**
	 * Test if the permission of the listing object is checked.
	 *
	 * @return void
	 */
	public function test_permission_of_listing_object_is_checked(): void {
		$this->test_listing->permission = 'do_not_allow';
		$this->test_listing->listings   = array( '/root/' => $this->entry( 'root' ) );

		$response = $this->request( $this->get_test_listing_params( '/root/' ) );
		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 'edlfw_not_allowed', $response->get_data()['code'] );
		$this->assertEmpty( $this->test_listing->requested );
	}

	/**
	 * Test if fields with an invalid type do not result in an error.
	 *
	 * @return void
	 */
	public function test_fields_with_invalid_type(): void {
		$this->test_listing->listings = array( '/root/' => $this->entry( 'root' ) );

		$params           = $this->get_test_listing_params( '/root/' );
		$params['fields'] = 'not-an-array';
		$response         = $this->request( $params );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( '/root/', $response->get_data() );
		$this->assertEquals( array(), $this->test_listing->get_fields() );
	}

	/**
	 * Test if a failed login results in the error texts.
	 *
	 * @return void
	 */
	public function test_failed_login(): void {
		$this->test_listing->login_result = false;

		$data = $this->request( $this->get_test_listing_params( '/root/' ) )->get_data();
		$this->assertEquals( array( 'Login failed.' ), $data['errors'] );
	}

	/**
	 * Test if error texts only contain safe HTML.
	 *
	 * @return void
	 */
	public function test_error_texts_are_sanitized(): void {
		$this->test_listing->error_texts = array( 'See <a href="https://example.com/">details</a><script>alert(1)</script>' );

		$data = $this->request( $this->get_test_listing_params( '/root/' ) )->get_data();
		$this->assertCount( 1, $data['errors'] );
		$this->assertStringContainsString( '<a href="https://example.com/">details</a>', $data['errors'][0] );
		$this->assertStringNotContainsString( '<script>', $data['errors'][0] );
	}

	/**
	 * Test if the complete tree is loaded in one request with all its sub-directories.
	 *
	 * @return void
	 */
	public function test_complete_tree_in_one_request(): void {
		$this->test_listing->listings = array(
			'/root/'          => $this->entry( 'root', array( '/root/a/', '/root/b/' ) ),
			'/root/a/'        => $this->entry( 'a', array( '/root/a/sub/' ) ),
			'/root/b/'        => $this->entry( 'b', array(), array( array( 'title' => 'file.pdf' ) ) ),
			'/root/a/sub/'    => $this->entry( 'sub' ),
		);

		$result = $this->load_tree( $this->get_test_listing_params( '/root/' ) );

		$this->assertEquals( 1, $result['requests'] );
		$tree = $result['tree'];
		$this->assertEquals( array( '/root/' ), array_keys( $tree ) );
		$this->assertEquals( array( '/root/a/', '/root/b/' ), array_keys( $tree['/root/']['dirs'] ) );
		$this->assertEquals( 'sub', $tree['/root/']['dirs']['/root/a/']['dirs']['/root/a/sub/']['title'] );
		$this->assertEquals( 'file.pdf', $tree['/root/']['dirs']['/root/b/']['files'][0]['title'] );

		// each directory is loaded exactly once.
		$this->assertEquals( array( '/root/', '/root/a/', '/root/b/', '/root/a/sub/' ), $this->test_listing->requested );
	}

	/**
	 * Test if a small time budget results in several requests with the same tree as one request.
	 *
	 * @return void
	 */
	public function test_small_time_budget_results_in_same_tree(): void {
		$this->test_listing->listings = array(
			'/root/'       => $this->entry( 'root', array( '/root/a/', '/root/b/' ) ),
			'/root/a/'     => $this->entry( 'a', array( '/root/a/sub/' ) ),
			'/root/b/'     => $this->entry( 'b' ),
			'/root/a/sub/' => $this->entry( 'sub' ),
		);
		$expected = $this->load_tree( $this->get_test_listing_params( '/root/' ) )['tree'];

		// load each directory in its own request.
		add_filter( self::hook( 'directory_listing_time_budget' ), '__return_zero' );
		$result = $this->load_tree( $this->get_test_listing_params( '/root/' ) );

		$this->assertEquals( 4, $result['requests'] );
		$this->assertEquals( $expected, $result['tree'] );
	}

	/**
	 * Test if the response tells how many directories must be loaded.
	 *
	 * @return void
	 */
	public function test_response_for_further_loading(): void {
		$this->test_listing->listings = array(
			'/root/'   => $this->entry( 'root', array( '/root/a/', '/root/b/' ) ),
			'/root/a/' => $this->entry( 'a' ),
			'/root/b/' => $this->entry( 'b' ),
		);
		add_filter( self::hook( 'directory_listing_time_budget' ), '__return_zero' );

		$data = $this->request( $this->get_test_listing_params( '/root/' ) )->get_data();
		$this->assertEquals(
			array(
				'directory_loading' => true,
				'directory_to_load' => 2,
			),
			$data
		);
	}

	/**
	 * Test if the loading could be canceled and returns the tree loaded so far.
	 *
	 * @return void
	 */
	public function test_cancel_loading(): void {
		$this->test_listing->listings = array(
			'/root/'   => $this->entry( 'root', array( '/root/a/', '/root/b/' ) ),
			'/root/a/' => $this->entry( 'a' ),
			'/root/b/' => $this->entry( 'b' ),
		);
		add_filter( self::hook( 'directory_listing_time_budget' ), '__return_zero' );

		$params = $this->get_test_listing_params( '/root/' );
		$this->request( $params );

		$params['restart']       = false;
		$params['cancelLoading'] = true;
		$data                    = $this->request( $params )->get_data();

		$this->assertArrayNotHasKey( 'directory_loading', $data );
		$this->assertArrayHasKey( '/root/', $data );
		$this->assertCount( 2, $this->test_listing->requested );
	}

	/**
	 * Test if a new loading process ignores the cached tree of a former (aborted) loading process.
	 *
	 * @return void
	 */
	public function test_restart_ignores_cached_tree(): void {
		$this->test_listing->listings = array( '/root/' => $this->entry( 'root' ) );

		// simulate the cached tree of an aborted loading process.
		$cache_key = Init::get_instance()->get_prefix() . '_' . get_current_user_id() . '_' . md5( '/root/' ) . '_tree';
		set_transient( $cache_key, array( '/root/' => $this->entry( 'outdated' ) ), DAY_IN_SECONDS );

		// the next part of a running loading process uses the cache.
		$params            = $this->get_test_listing_params( '/root/' );
		$params['restart'] = false;
		$this->assertEquals( 'outdated', $this->request( $params )->get_data()['/root/']['title'] );

		// a new loading process does not use it.
		set_transient( $cache_key, array( '/root/' => $this->entry( 'outdated' ) ), DAY_IN_SECONDS );
		$params['restart'] = true;
		$this->assertEquals( 'root', $this->request( $params )->get_data()['/root/']['title'] );
	}

	/**
	 * Test if the cache is removed after the tree has been loaded.
	 *
	 * @return void
	 */
	public function test_cache_is_removed_after_loading(): void {
		$this->test_listing->listings = array(
			'/root/'   => $this->entry( 'root', array( '/root/a/' ) ),
			'/root/a/' => $this->entry( 'a' ),
		);
		add_filter( self::hook( 'directory_listing_time_budget' ), '__return_zero' );

		$this->load_tree( $this->get_test_listing_params( '/root/' ) );

		$cache_key = Init::get_instance()->get_prefix() . '_' . get_current_user_id() . '_' . md5( '/root/' ) . '_tree';
		$this->assertFalse( get_transient( $cache_key ) );
	}

	/**
	 * Test if a directory, which could not be loaded, is added as empty directory.
	 *
	 * @return void
	 */
	public function test_directory_which_could_not_be_loaded(): void {
		$this->test_listing->listings = array(
			'/root/' => $this->entry( 'root', array( '/root/not-readable/' ) ),
		);

		$tree = $this->load_tree( $this->get_test_listing_params( '/root/' ) )['tree'];
		$this->assertEquals(
			array(
				'title' => 'not-readable',
				'files' => array(),
				'dirs'  => array(),
			),
			$tree['/root/']['dirs']['/root/not-readable/']
		);
	}

	/**
	 * Test if a sub-directory, which links back to the main directory, does not result in an endless loop or an empty tree.
	 *
	 * @return void
	 */
	public function test_directory_cycle(): void {
		$this->test_listing->listings = array(
			'/root/'   => $this->entry( 'root', array( '/root/a/' ) ),
			'/root/a/' => $this->entry( 'a', array( '/root/' ) ),
		);

		$result = $this->load_tree( $this->get_test_listing_params( '/root/' ) );

		$this->assertEquals( 1, $result['requests'] );
		$this->assertEquals( array( '/root/' ), array_keys( $result['tree'] ) );
		$this->assertEquals( 'a', $result['tree']['/root/']['dirs']['/root/a/']['title'] );
		$this->assertEquals( array( '/root/', '/root/a/' ), $this->test_listing->requested );
	}

	/**
	 * Test if a listing object could return the complete tree at once.
	 *
	 * @return void
	 */
	public function test_listing_with_completed_tree(): void {
		$this->test_listing->listings = array(
			'/root/' => array(
				'completed' => true,
				'/root/'    => $this->entry( 'root', array( '/root/a/' ) ),
				'/root/a/'  => $this->entry( 'a' ),
			),
		);

		$result = $this->load_tree( $this->get_test_listing_params( '/root/' ) );

		$this->assertEquals( 1, $result['requests'] );
		$this->assertEquals( array( '/root/' ), array_keys( $result['tree'] ) );
		$this->assertEquals( 'a', $result['tree']['/root/']['dirs']['/root/a/']['title'] );
		$this->assertEquals( array( '/root/' ), $this->test_listing->requested );
	}

	/**
	 * Test if the credentials are saved as directory archive if requested.
	 *
	 * @return void
	 */
	public function test_save_credentials(): void {
		$this->test_listing->listings = array( '/root/' => $this->entry( 'root' ) );

		$params                    = $this->get_test_listing_params( '/root/' );
		$params['saveCredentials'] = true;
		$params['fields']          = array( 'login' => array( 'value' => 'my-login' ) );
		$this->request( $params );

		$term = get_term_by( 'slug', md5( 'edlfw_test_listing/root/' ), \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() );
		$this->assertInstanceOf( \WP_Term::class, $term );

		$entry = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term->term_id );
		$this->assertEquals( 'my-login', $entry['fields']['login']['value'] );
	}

	/**
	 * Test if a saved term is used for the request.
	 *
	 * @return void
	 */
	public function test_load_saved_term(): void {
		$this->test_listing->listings = array( '/saved/' => $this->entry( 'saved' ) );
		$term_id                      = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'edlfw_test_listing', '/saved/', array( 'login' => array( 'value' => 'saved-login' ) ) );

		$params         = $this->get_test_listing_params( '/other/' );
		$params['term'] = $term_id;
		$data           = $this->request( $params )->get_data();

		$this->assertArrayHasKey( '/saved/', $data );
		$this->assertEquals( 'saved-login', $this->test_listing->get_fields()['login']['value'] );
	}

	/**
	 * Test if the tree of a local directory is loaded completely.
	 *
	 * @return void
	 */
	public function test_local_directory(): void {
		$base = $this->create_directory(
			array(
				'doc.pdf' => 'pdf',
				'a/'      => array(
					'note.txt' => 'note',
					'sub/'     => array( 'deep.txt' => 'deep' ),
				),
				'b/'      => array(),
			)
		);

		$result = $this->load_tree( array( 'directory' => $base ) );
		$tree   = $result['tree'];

		$this->assertEquals( 1, $result['requests'] );
		$this->assertEquals( array( $base ), array_keys( $tree ) );
		$this->assertEqualsCanonicalizing( array( $base . 'a/', $base . 'b/' ), array_keys( $tree[ $base ]['dirs'] ) );
		$this->assertEquals( 'doc.pdf', $tree[ $base ]['files'][0]['title'] );
		$this->assertEquals( 'deep.txt', $tree[ $base ]['dirs'][ $base . 'a/' ]['dirs'][ $base . 'a/sub/' ]['files'][0]['title'] );
	}
}
