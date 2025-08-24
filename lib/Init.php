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
     * The capability.
     *
     * @var string
     */
    private string $capability = 'manage_options';

    /**
     * Instance of actual object.
     *
     * @var ?Init
     */
    private static ?Init $instance = null;

    /**
     * List of translations this package delivers.
     *
     * @var array<string,mixed>
     */
    private array $translations = array();

    /**
     * List of custom translations the calling plugin uses.
     *
     * @var array<string,mixed>
     */
    private array $custom_translations = array();

    /**
     * The archive state (true to enable it, false to disable it).
     *
     * @var bool
     */
    private bool $archive_state = true;

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
        if ( ! empty( $this->get_page_hook() ) && ! in_array( $hook, array( $this->get_page_hook(), 'edit-tags.php', 'term.php' ), true ) ) {
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
            (string) filemtime( $admin_css_path )
        );

        // get parsed endpoint URL.
        $rest_url = rest_url( 'easy-directory-listing-for-wordpress/v1/directory' );

        // remove domain and /wp-json/ from URL.
        $rest_url = str_replace( trailingslashit( get_option( 'siteurl' ) ) . 'wp-json', '', $rest_url );

        // add php-vars to our js-script.
        wp_localize_script(
            'easy-directory-listing-for-wordpress',
            'edlfwJsVars',
            array_merge(
                array(
                    'get_directory_endpoint' => $rest_url,
                ),
                $this->get_translations()
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

    /**
     * Set all basic translations.
     *
     * @return void
     */
    private function get_basic_translations(): void {
        $this->translations = array(
            'is_loading'          => 'Please wait, directory is loading.',
            'cancel'              => 'Cancel',
            'please_wait'         => 'Cancel loading, please wait',
            'loading_directory'   => '1 sub-directory to load',
            'loading_directories' => '%1$d sub-directories to load',
            'could_not_load'      => 'Directory could not be loaded.',
            'reload'              => 'Reload',
            'import_directory'    => 'Import active directory',
            'actions'             => 'Actions',
            'filename'            => 'Filename',
            'filesize'            => 'Size',
            'date'                => 'Date',
            'config_missing'      => 'Configuration for Directory Listing missing!',
            'nonce_missing'       => 'Secure token for Directory Listing missing!',
            'empty_directory'     => 'Loaded an empty directory.',
            'error_title'         => 'The following error occurred:',
            'errors_title'        => 'The following errors occurred:',
            'serverside_error'    => 'Incorrect response received from the server, possibly a server-side error.',
            'directory_archive'   => array(
                'connect_now'     => 'Open now',
                'labels'          => array(
                    'name'          => 'Directory Credentials',
                    'singular_name' => 'Directory Credential',
                    'search_items'  => 'Search Directory Credential',
                    'edit_item'     => 'Edit Directory Credential',
                    'update_item'   => 'Update Directory Credential',
                    'menu_name'     => 'Directory Credentials',
                    'back_to_items' => 'Back to Directory Credentials',
                ),
                'messages'        => array(
                    'updated' => 'Directory Credential updated.',
                    'deleted' => 'Directory Credential deleted.',
                ),
                'type'            => 'Type',
                'connect'         => 'Connect',
                'type_not_loaded' => 'Type could not be loaded!',
                'login'           => 'Login',
                'password'        => 'Password',
                'api_key'         => 'API Key',
            ),
            'form_file'           => array(
                'title'       => 'Enter the path to a local file',
                'description' => '',
                'url'         => array(
                    'label' => 'File',
                ),
                'button'      => array(
                    'label' => 'Show file',
                ),
            ),
            'form_api'            => array(
                'title'            => 'Enter your credentials',
                'description'      => '',
                'url'              => array(
                    'label' => 'Login',
                ),
                'key'              => array(
                    'label' => 'Password',
                ),
                'save_credentials' => array(
                    'label' => 'Save this credentials in directory archive',
                ),
                'button'           => array(
                    'label' => 'Show directory',
                ),
            ),
            'form_login'          => array(
                'title'            => 'Enter your credentials',
                'description'      => '',
                'url'              => array(
                    'label' => 'URL',
                ),
                'login'            => array(
                    'label' => 'Login',
                ),
                'password'         => array(
                    'label' => 'Password',
                ),
                'save_credentials' => array(
                    'label' => 'Save this credentials in directory archive',
                ),
                'button'           => array(
                    'label' => 'Show directory',
                ),
            ),
            'aws_s3_api'            => array(
                'title'            => 'Enter your credentials',
                'description'      => '',
                'access_key'              => array(
                    'label' => 'Access Key',
                ),
                'secret_key'              => array(
                    'label' => 'Secret Key',
                ),
                'bucket'              => array(
                    'label' => 'Bucket',
                ),
                'save_credentials' => array(
                    'label' => 'Save this credentials in directory archive',
                ),
                'button'           => array(
                    'label' => 'Show directory',
                ),
            ),
            'services'            => array(
                'local' => array(
                    'label' => 'Local server directory',
                    'title' => 'Choose file(s) from local server directory',
                ),
            ),
        );
    }

    /**
     * Return the translations for this listing.
     *
     * @return array<string,mixed>
     */
    public function get_translations(): array {
        if ( empty( $this->translations ) ) {
            // initialize all basic translations.
            $this->get_basic_translations();
        }

        // return the translations as mix of basic and custom translations.
        return array_replace_recursive( $this->translations, $this->custom_translations );
    }

    /**
     * Set custom translations.
     *
     * @param array<string,mixed> $translations List of translations.
     *
     * @return void
     */
    public function set_translations( array $translations ): void {
        $this->custom_translations = $translations;
    }

    /**
     * Return the state of the archive.
     *
     * @return bool
     */
    public function is_archive_enabled(): bool {
        return $this->archive_state;
    }

    /**
     * Set archive state.
     *
     * @param bool $archive_state The new state of the archive.
     *
     * @return void
     */
    public function set_archive_state( bool $archive_state ): void {
        $this->archive_state = $archive_state;
    }

    /**
     * Return the capability.
     *
     * @return string
     */
    public function get_capability(): string {
        return $this->capability;
    }

    /**
     * Set the capability.
     *
     * @param string $capability The capability to use.
     *
     * @return void
     */
    public function set_capability( string $capability ): void {
        $this->capability = $capability;
    }
}
