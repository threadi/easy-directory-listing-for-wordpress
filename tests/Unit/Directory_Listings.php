<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Directory_Listings.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit;

use easyDirectoryListingForWordPress\Listings\Local;
use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;
use easyDirectoryListingForWordPress\Tests\Fixtures\Test_Listing;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Directory_Listings.
 */
class Directory_Listings extends easyDirectoryListingTests {
	/**
	 * The directory for the previews.
	 *
	 * @var string
	 */
	private string $preview_dir = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->preview_dir = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'edlfw/';
		wp_mkdir_p( $this->preview_dir );
	}

	/**
	 * Return the object.
	 *
	 * @return \easyDirectoryListingForWordPress\Directory_Listings
	 */
	private function get_obj(): \easyDirectoryListingForWordPress\Directory_Listings {
		return \easyDirectoryListingForWordPress\Directory_Listings::get_instance();
	}

	/**
	 * Create a preview file with the given age.
	 *
	 * @param string $name The file name.
	 * @param int    $age The age in seconds.
	 *
	 * @return string The path of the file.
	 */
	private function create_preview( string $name, int $age ): string {
		$path = $this->preview_dir . $name;
		file_put_contents( $path, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		touch( $path, time() - $age );
		return $path;
	}

	/**
	 * Test if the local listing is one of the listing objects.
	 *
	 * @return void
	 */
	public function test_get_directory_listings_objects(): void {
		$this->assertContains( Local::get_instance(), $this->get_obj()->get_directory_listings_objects() );
	}

	/**
	 * Test if further listing objects could be added by filter.
	 *
	 * @return void
	 */
	public function test_add_listing_object_by_filter(): void {
		$test_listing = new Test_Listing();
		add_filter( self::hook( 'directory_listing_objects' ), array( $test_listing, 'register' ) );

		$this->assertContains( $test_listing, $this->get_obj()->get_directory_listings_objects() );
		$this->assertSame( $test_listing, $this->get_obj()->get_directory_listing_object_by_name( 'edlfw_test_listing' ) );
	}

	/**
	 * Test if a listing object could be loaded by its name.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_object_by_name(): void {
		$this->assertSame( Local::get_instance(), $this->get_obj()->get_directory_listing_object_by_name( 'local' ) );
		$this->assertFalse( $this->get_obj()->get_directory_listing_object_by_name( 'unknown' ) );
	}

	/**
	 * Test if the archive URL links to the taxonomy.
	 *
	 * @return void
	 */
	public function test_get_directory_archive_url(): void {
		$url = $this->get_obj()->get_directory_archive_url();
		$this->assertStringContainsString( 'edit-tags.php', $url );
		$this->assertStringContainsString( 'taxonomy=' . \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), $url );
	}

	/**
	 * Test if the cleanup deletes only previews, which are older than 1 day.
	 *
	 * @return void
	 */
	public function test_cleanup_deletes_old_previews(): void {
		$old = $this->create_preview( 'edlfw-test-old.jpg', 2 * DAY_IN_SECONDS );
		$new = $this->create_preview( 'edlfw-test-new.jpg', HOUR_IN_SECONDS );

		$this->get_obj()->cleanup();
		clearstatcache();

		$this->assertFileDoesNotExist( $old );
		$this->assertFileExists( $new );
		unlink( $new );
	}

	/**
	 * Test if the cleanup runs only once per hour.
	 *
	 * @return void
	 */
	public function test_cleanup_runs_once_per_hour(): void {
		$this->get_obj()->cleanup();

		$old = $this->create_preview( 'edlfw-test-old.jpg', 2 * DAY_IN_SECONDS );
		$this->get_obj()->cleanup();
		clearstatcache();

		$this->assertFileExists( $old );
		unlink( $old );
	}
}
