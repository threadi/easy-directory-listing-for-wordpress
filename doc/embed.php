<?php
/**
 * File which demonstrate how to add Easy Directory Listing for WordPress in wp-admin.
 *
 * @package easy-directory-listing-for-wordpress
 */

/**
 * Initialize the handler (e.g. in init-hook).
 */
add_action( 'init', function() {
	require_once trailingslashit( plugin_dir_path( __FILE__ ) ) . 'vendor/threadi/easy-directory-listing-for-wordpress/lib/Init.php';
	$directory_listing_obj = \easyDirectoryListingForWordPress\Init::get_instance();
	$directory_listing_obj->set_path( trailingslashit( plugin_dir_path( __FILE__ ) ) );
	$directory_listing_obj->set_url( plugin_dir_url( __FILE__ ) );
	$directory_listing_obj->set_prefix( 'your-plugin-slug' );
	$directory_listing_obj->set_nonce_name( 'your-custom-nonce' );
	$directory_listing_obj->init();
});

/**
 * Add output where you want. Example for local listing, which is shipped with this package.
 *
 * @return void
 */
function custom_show_directory_listing(): void {
	// get local listing object.
	$directory_listing_obj = \easyDirectoryListingForWordPress\Listings\Local::get_instance();

	// get config of it and add the nonce.
	$config = $directory_listing_obj->get_config();
	$config['nonce'] = wp_create_nonce( 'your-custom-nonce' );

	// output.
	?>
	 <div id="easy-directory-listing-for-wordpress" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
	<?php
}
