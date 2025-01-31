<?php
/**
 * File to handle the initialization of directory listing in WordPress.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the initialization of directory listing.
 */
class Init {
	/**
	 * Prefix to use for hooks.
	 *
	 * @var string
	 */
	private string $prefix = 'edlfw';

	/**
	 * The nonce name,
	 *
	 * @var string
	 */
	private string $nonce_name = '';

	/**
	 * The path.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * The URL.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * The preview state (enabled by default).
	 *
	 * @var bool
	 */
	private bool $preview = true;

	/**
	 * The page hook.
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * The menu slug.
	 *
	 * @var string
	 */
	private string $menu_slug = '';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Init
	 */
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// define constants.
		define( 'EDLFW_HASH', 'edlfw_hash' );
		define( 'EDLFW_SODIUM_HASH', 'edlfw_sodium_hash' );

		// add scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );

		// initialize the taxonomy.
		Taxonomy::get_instance()->init();

		// initialize the supported listing.
		Directory_Listings::get_instance()->init();

		// initialize the REST route.
		Rest::get_instance()->init( $this );
	}

	/**
	 * Return the path.
	 *
	 * @return string
	 */
	private function get_path(): string {
		return $this->path;
	}

	/**
	 * Set the path to use.
	 *
	 * @param string $path The path.
	 *
	 * @return void
	 */
	public function set_path( string $path ): void {
		$this->path = trailingslashit( $path );
	}

	/**
	 * Return the URL.
	 *
	 * @return string
	 */
	private function get_url(): string {
		return $this->url;
	}

	/**
	 * Set the URL to use.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = trailingslashit( $url );
	}

	/**
	 * Add the directory listing script.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_scripts( string $hook ): void {
		// bail if page hook is set and does not match the hook.
		if( ! empty( $this->get_page_hook() ) && ! in_array( $hook, array( $this->get_page_hook(), 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		// define paths: adjust if necessary.
		$path = $this->get_path() . 'vendor/threadi/easy-directory-listing-for-wordpress/';
		$url  = $this->get_url() . 'vendor/threadi/easy-directory-listing-for-wordpress/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// get assets path.
		$script_asset_path = $path . 'build/index.asset.php';

		// bail if assets does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		// embed the dialog-components JS-script.
		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'easy-directory-listing-for-wordpress',
			$url . 'build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// embed the dialog-components CSS-script.
		$admin_css      = $url . 'build/style-index.css';
		$admin_css_path = $path . 'build/style-index.css';
		wp_enqueue_style(
			'easy-directory-listing-for-wordpress',
			$admin_css,
			array( 'wp-components' ),
			filemtime( $admin_css_path )
		);

		// get parsed endpoint URL.
		$endpoint_parsed_url = wp_parse_url( rest_url( 'easy-directory-listing-for-wordpress/v1/directory' ) );

		// add php-vars to our js-script.
		wp_localize_script(
			'easy-directory-listing-for-wordpress',
			'edlfwJsVars',
			array(
				'get_directory_endpoint' => str_replace( '/wp-json/', '', $endpoint_parsed_url['path'] ),
			)
		);
	}

	/**
	 * Return the configured prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return $this->prefix;
	}

	/**
	 * Set prefix.
	 *
	 * @param string $prefix The prefix.
	 *
	 * @return void
	 */
	public function set_prefix( string $prefix ): void {
		$this->prefix = $prefix;
	}

	/**
	 * Return the nonce name.
	 *
	 * @return string
	 */
	public function get_nonce_name(): string {
		return $this->nonce_name;
	}

	/**
	 * Set name of nonce.
	 *
	 * @param string $nonce_name The nonce name.
	 *
	 * @return void
	 */
	public function set_nonce_name( string $nonce_name ): void {
		$this->nonce_name = $nonce_name;
	}

	/**
	 * Return whether preview is enabled.
	 *
	 * @return bool
	 */
	public function is_preview_enabled(): bool {
		return $this->preview;
	}

	/**
	 * Set preview state.
	 *
	 * @param bool $state The state (true to enable).
	 *
	 * @return void
	 */
	public function set_preview_state( bool $state ): void {
		$this->preview = $state;
	}

	/**
	 * Return the page hook.
	 *
	 * @return string
	 */
	public function get_page_hook(): string {
		return $this->page_hook;
	}

	/**
	 * Set the page hook where the scripts should be loaded.
	 *
	 * @param string $page_hook The page hook.
	 *
	 * @return void
	 */
	public function set_page_hook( string $page_hook ): void {
		$this->page_hook = $page_hook;
	}

	/**
	 * Return the menu slug.
	 *
	 * @return string
	 */
	public function get_menu_slug(): string {
		return $this->menu_slug;
	}

	/**
	 * Set the menu slug where the listing should be output.
	 *
	 * @param string $menu_slug The menu slug.
	 *
	 * @return void
	 */
	public function set_menu_slug( string $menu_slug ): void {
		$this->menu_slug = $menu_slug;
	}
}
