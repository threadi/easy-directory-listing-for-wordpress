<?php
/**
 * Tests for class easyDirectoryListingForWordPress\Listings\Local.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress\Tests\Unit\Listings;

use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Tests\easyDirectoryListingTests;
use ReflectionMethod;

/**
 * Object to test functions in class easyDirectoryListingForWordPress\Listings\Local.
 */
class Local extends easyDirectoryListingTests {
	/**
	 * Return the local listing object.
	 *
	 * @return \easyDirectoryListingForWordPress\Listings\Local
	 */
	private function get_obj(): \easyDirectoryListingForWordPress\Listings\Local {
		return \easyDirectoryListingForWordPress\Listings\Local::get_instance();
	}

	/**
	 * Return the file entry with the given title from a listing.
	 *
	 * @param array<int|string,mixed> $listing The listing.
	 * @param string                  $title The title.
	 *
	 * @return array<string,mixed>
	 */
	private function get_file( array $listing, string $title ): array {
		foreach ( $listing['files'] as $file ) {
			if ( $title === $file['title'] ) {
				return $file;
			}
		}
		$this->fail( 'File ' . $title . ' not found in listing.' );
	}

	/**
	 * Return the absolute path of the preview file from the HTML code of a preview.
	 *
	 * @param string $preview The HTML code of the preview.
	 *
	 * @return string
	 */
	private function get_preview_path( string $preview ): string {
		$this->assertMatchesRegularExpression( '/<img src="[^"]+" alt="">/', $preview );
		preg_match( '/src="([^"]+)"/', $preview, $matches );
		return trailingslashit( wp_get_upload_dir()['basedir'] ) . 'edlfw/' . basename( $matches[1] );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$this->assertEquals( 'local', $this->get_obj()->get_name() );
	}

	/**
	 * Test if the default directory is the WordPress directory.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$this->assertEquals( 'file://' . trailingslashit( ABSPATH ), $this->get_obj()->get_directory() );

		$this->get_obj()->set_directory( '/tmp/' );
		$this->assertEquals( '/tmp/', $this->get_obj()->get_directory() );
	}

	/**
	 * Test if the listing contains the files and directories with all necessary data.
	 *
	 * @return void
	 */
	public function test_listing_contains_files_and_directories(): void {
		$base = $this->create_directory(
			array(
				'hello.txt' => 'hello',
				'sub/'      => array(),
			)
		);

		$listing = $this->get_obj()->get_directory_listing( $base );

		$this->assertEquals( basename( $base ), $listing['title'] );
		$this->assertEquals( array( $base . 'sub/' => array( 'title' => 'sub' ) ), $listing['dirs'] );

		$file = $this->get_file( $listing, 'hello.txt' );
		$this->assertEquals( $base . 'hello.txt', $file['file'] );
		$this->assertEquals( 5, $file['filesize'] );
		$this->assertEquals( 'text/plain', $file['mime-type'] );
		$this->assertNotEmpty( $file['last-modified'] );
		$this->assertStringContainsString( 'data-type="text/plain"', $file['icon'] );
		$this->assertEquals( '', $file['preview'] );
	}

	/**
	 * Test if the directory is also usable with "file://".
	 *
	 * @return void
	 */
	public function test_listing_with_file_protocol(): void {
		$base = $this->create_directory( array( 'hello.txt' => 'hello' ) );

		$listing = $this->get_obj()->get_directory_listing( 'file://' . $base );
		$this->assertEquals( 'file://' . $base . 'hello.txt', $this->get_file( $listing, 'hello.txt' )['file'] );
	}

	/**
	 * Test if files with not allowed file types are not listed.
	 *
	 * @return void
	 */
	public function test_not_allowed_file_types_are_not_listed(): void {
		$base = $this->create_directory(
			array(
				'allowed.pdf'  => 'pdf',
				'script.php'   => '<?php',
				'without-type' => 'x',
			)
		);

		$listing = $this->get_obj()->get_directory_listing( $base );
		$this->assertEquals( array( 'allowed.pdf' ), array_column( $listing['files'], 'title' ) );
	}

	/**
	 * Test if a not existing directory results in an empty listing.
	 *
	 * @return void
	 */
	public function test_not_existing_directory(): void {
		$this->assertEquals( array(), $this->get_obj()->get_directory_listing( '/not/existing/directory/' ) );
		$this->assertEmpty( $this->get_obj()->get_errors() );
	}

	/**
	 * Return the test cases for directories with other stream wrappers.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_other_stream_wrappers(): array {
		return array(
			'ftp'  => array( 'ftp://example.com/' ),
			'http' => array( 'https://example.com/' ),
			'phar' => array( 'phar:///tmp/example.phar/' ),
		);
	}

	/**
	 * Test if other stream wrappers than "file://" are not used.
	 *
	 * @dataProvider provide_other_stream_wrappers
	 *
	 * @param string $directory The directory.
	 *
	 * @return void
	 */
	public function test_other_stream_wrappers_are_not_used( string $directory ): void {
		$this->assertEquals( array(), $this->get_obj()->get_directory_listing( $directory ) );
		$this->assertCount( 1, $this->get_obj()->get_errors() );
	}

	/**
	 * Test if a symlink to the directory itself or one of its parents is not listed, as this would result in an endless loop.
	 *
	 * @return void
	 */
	public function test_symlink_to_parent_is_not_listed(): void {
		$base = $this->create_directory(
			array(
				'a/' => array( 'sub/' => array() ),
				'b/' => array(),
			)
		);
		symlink( $base, $base . 'a/sub/to-base' );
		symlink( $base . 'a/sub/', $base . 'a/sub/to-itself' );
		symlink( $base . 'b/', $base . 'a/sub/to-other' );

		$listing = $this->get_obj()->get_directory_listing( $base . 'a/sub/' );
		$this->assertEquals( array( $base . 'a/sub/to-other/' ), array_keys( $listing['dirs'] ) );
	}

	/**
	 * Test if a local directory with a symlink loop is loaded completely without an endless loop.
	 *
	 * @return void
	 */
	public function test_tree_with_symlink_loop(): void {
		$base = $this->create_directory( array( 'a/' => array( 'file.txt' => 'x' ) ) );
		symlink( $base, $base . 'a/loop' );
		$this->do_login();

		$result = $this->load_tree( array( 'directory' => $base ) );
		$this->assertEquals( 1, $result['requests'] );
		$this->assertEquals( array( $base . 'a/' ), array_keys( $result['tree'][ $base ]['dirs'] ) );
	}

	/**
	 * Test if a preview is generated for images.
	 *
	 * @return void
	 */
	public function test_preview_for_images(): void {
		$base = $this->create_directory( array() );
		$this->create_image( $base . 'image.jpg' );

		$file = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' );

		$this->assertEquals( 'image/jpeg', $file['mime-type'] );
		$this->assertFileExists( $this->get_preview_path( $file['preview'] ) );
	}

	/**
	 * Test if the preview of an image is generated only once.
	 *
	 * @return void
	 */
	public function test_preview_is_generated_once(): void {
		$base = $this->create_directory( array() );
		$this->create_image( $base . 'image.jpg' );

		$first   = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' )['preview'];
		$preview = $this->get_preview_path( $first );

		// mark the preview as old: a new generation would change this time.
		touch( $preview, 1000 );

		$second = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' )['preview'];
		clearstatcache();

		$this->assertEquals( $first, $second );
		$this->assertEquals( 1000, filemtime( $preview ) );
	}

	/**
	 * Test if a changed image gets a new preview.
	 *
	 * @return void
	 */
	public function test_changed_image_gets_new_preview(): void {
		$base = $this->create_directory( array() );
		$this->create_image( $base . 'image.jpg' );
		touch( $base . 'image.jpg', time() - HOUR_IN_SECONDS );

		$first = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' )['preview'];

		touch( $base . 'image.jpg', time() );
		clearstatcache();

		$second = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' )['preview'];
		$this->assertNotEquals( $first, $second );
		$this->assertFileExists( $this->get_preview_path( $second ) );
	}

	/**
	 * Test if images with the same name in different directories get different previews.
	 *
	 * @return void
	 */
	public function test_images_with_same_name_get_different_previews(): void {
		$base = $this->create_directory(
			array(
				'a/' => array(),
				'b/' => array(),
			)
		);
		$this->create_image( $base . 'a/image.jpg' );
		$this->create_image( $base . 'b/image.jpg' );

		$preview_a = $this->get_file( $this->get_obj()->get_directory_listing( $base . 'a/' ), 'image.jpg' )['preview'];
		$preview_b = $this->get_file( $this->get_obj()->get_directory_listing( $base . 'b/' ), 'image.jpg' )['preview'];

		$this->assertNotEquals( $preview_a, $preview_b );
	}

	/**
	 * Test if no preview is generated for images, which are larger than the configured max file size.
	 *
	 * @return void
	 */
	public function test_no_preview_for_large_images(): void {
		$base = $this->create_directory( array() );
		$this->create_image( $base . 'image.jpg' );
		add_filter( self::hook( 'service_local_preview_max_filesize' ), static fn() => 10 );

		$file = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' );
		$this->assertEquals( '', $file['preview'] );
	}

	/**
	 * Test if no preview is generated for files, which only have the extension of an image.
	 *
	 * @return void
	 */
	public function test_no_preview_for_fake_images(): void {
		$base = $this->create_directory( array( 'fake.jpg' => 'this is not an image' ) );

		$file = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'fake.jpg' );
		$this->assertEquals( '', $file['preview'] );
	}

	/**
	 * Test if no preview is generated if the preview is disabled.
	 *
	 * @return void
	 */
	public function test_no_preview_if_disabled(): void {
		$base = $this->create_directory( array() );
		$this->create_image( $base . 'image.jpg' );

		Init::get_instance()->set_preview_state( false );
		try {
			$file = $this->get_file( $this->get_obj()->get_directory_listing( $base ), 'image.jpg' );
		} finally {
			Init::get_instance()->set_preview_state( true );
		}
		$this->assertEquals( '', $file['preview'] );
	}

	/**
	 * Return the file names to compare the mime type detection with WordPress.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_file_names(): array {
		return array(
			'jpg'                 => array( 'image.jpg' ),
			'upper case'          => array( 'IMAGE.JPG' ),
			'mixed case'          => array( 'Document.Pdf' ),
			'second extension'    => array( 'jpeg|jpg|jpe' ),
			'only extension'      => array( '.jpg' ),
			'multiple dots'       => array( 'archive.tar.gz' ),
			'dot in name'         => array( 'my.file.txt' ),
			'without extension'   => array( 'README' ),
			'php'                 => array( 'script.php' ),
			'phtml'               => array( 'script.phtml' ),
			'not allowed but txt' => array( 'script.php.txt' ),
			'mp4'                 => array( 'movie.mp4' ),
			'docx'                => array( 'letter.docx' ),
			'empty extension'     => array( 'file.' ),
		);
	}

	/**
	 * Test if the mime type detection returns the same as wp_check_filetype().
	 *
	 * @dataProvider provide_file_names
	 *
	 * @param string $filename The file name.
	 *
	 * @return void
	 */
	public function test_mime_type_matches_wordpress( string $filename ): void {
		$method = new ReflectionMethod( $this->get_obj(), 'get_mime_type' );

		$expected = wp_check_filetype( $filename )['type'];
		$this->assertEquals( false === $expected ? '' : $expected, $method->invoke( $this->get_obj(), $filename ) );
	}

	/**
	 * Test if the WordPress core files and directories are hidden and other files are not.
	 *
	 * @return void
	 */
	public function test_hide_files(): void {
		$obj = $this->get_obj();

		// WordPress files and directories are hidden.
		$this->assertTrue( $obj->hide_files( false, 'file://' . ABSPATH . 'wp-config.php' ) );
		$this->assertTrue( $obj->hide_files( false, 'file://' . ABSPATH . 'wp-admin' ) );
		$this->assertTrue( $obj->hide_files( false, 'file://' . ABSPATH . WPINC ) );
		$this->assertTrue( $obj->hide_files( false, 'file://' . WP_CONTENT_DIR ) );
		$this->assertTrue( $obj->hide_files( false, 'file://' . WP_CONTENT_DIR . '/uploads/image.jpg' ) );

		// other files are not hidden.
		$this->assertFalse( $obj->hide_files( false, 'file://' . ABSPATH . 'my-files' ) );
		$this->assertFalse( $obj->hide_files( false, '/tmp/wp-config.php' ) );

		// the result of other filters is kept.
		$this->assertTrue( $obj->hide_files( true, 'file://' . ABSPATH . 'my-files' ) );
	}

	/**
	 * Test if the WordPress files are not listed for the WordPress directory.
	 *
	 * @return void
	 */
	public function test_wordpress_files_are_not_listed(): void {
		$listing = $this->get_obj()->get_directory_listing( $this->get_obj()->get_directory() );

		$this->assertArrayNotHasKey( 'file://' . ABSPATH . 'wp-admin/', $listing['dirs'] );
		$this->assertArrayNotHasKey( 'file://' . ABSPATH . 'wp-content/', $listing['dirs'] );
		$this->assertArrayNotHasKey( 'file://' . ABSPATH . WPINC . '/', $listing['dirs'] );
		$this->assertNotContains( 'wp-config.php', array_column( $listing['files'], 'title' ) );
	}

	/**
	 * Test if files are hidden by the filter.
	 *
	 * @return void
	 */
	public function test_hide_files_by_filter(): void {
		$base = $this->create_directory(
			array(
				'visible.txt' => 'x',
				'hidden.txt'  => 'x',
			)
		);
		add_filter( self::hook( 'service_local_hide_file' ), static fn( bool $hide, string $path ) => $hide || str_ends_with( $path, 'hidden.txt' ), 10, 2 );

		$listing = $this->get_obj()->get_directory_listing( $base );
		$this->assertEquals( array( 'visible.txt' ), array_column( $listing['files'], 'title' ) );
	}
}
