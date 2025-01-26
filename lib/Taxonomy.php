<?php
/**
 * File to handle the taxonomy of directory listing in WordPress.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the taxonomy.
 */
class Taxonomy {
	/**
	 * The name.
	 *
	 * @var string
	 */
	private string $name = 'edlfw_archive';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Taxonomy
	 */
	private static ?Taxonomy $instance = null;

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
	 * @return Taxonomy
	 */
	public static function get_instance(): Taxonomy {
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
		// register taxonomy for credential archive.
		add_action( 'init', array( $this, 'register' ) );

		// change table.
		add_filter( 'manage_edit-' . $this->get_name() . '_columns', array( $this, 'set_table_columns' ) );
		add_filter( 'manage_' . $this->get_name() . '_custom_column', array( $this, 'set_table_column_content' ), 10, 3 );
		add_filter( $this->get_name() . '_row_actions', array( $this, 'set_actions' ), 10, 2 );
		add_action( $this->get_name() . '_edit_form_fields', array( $this, 'set_fields' ) );
		add_action( 'edit_term', array( $this, 'save_fields' ), 10, 3 );

	}

	/**
	 * Return the name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Register our own taxonomy for the credential archive.
	 *
	 * @return void
	 */
	public function register(): void {
		// set labels for the taxonomy.
		$labels = array(
			'name'          => _x( 'Directory Credentials', 'taxonomy general name' ),
			'singular_name' => _x( 'Directory Credential', 'taxonomy singular name' ),
			'search_items'  => __( 'Search Directory Credential' ),
			'edit_item'     => __( 'Edit Directory Credential' ),
			'update_item'   => __( 'Update Directory Credential' ),
			'menu_name'     => __( 'Directory Credentials' ),
		);

		// set configuration for this taxonomy.
		$configuration = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false,
			'query_var'         => false,
			'public' => false
		);

		// register the taxonomy.
		register_taxonomy( $this->get_name(), array(), $configuration );
	}

	/**
	 * Show the type in table.
	 *
	 * @param string $content The content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function set_table_column_content( string $content, string $column_name, int $term_id ): string {
		// get listing object if this is one of our columns.
		$listing_obj = false;
		if( in_array( $column_name, array( 'type', 'connect' ), true ) ) {
			// get the type name.
			$type = get_term_meta( $term_id, 'type', true );

			// get the listing object by this name.
			$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

			// bail if no object could be found.
			if ( ! $listing_obj ) {
				return '';
			}
		}

		// check if this is the type column.
		if( 'type' === $column_name && $listing_obj ) {
			// show the label.
			return $listing_obj->get_label();
		}

		// check if this is the connect column.
		if( 'connect' === $column_name && $listing_obj ) {
			// get the connect URL.
			$url = add_query_arg(
				array(
					'page' => Init::get_instance()->get_menu_slug(),
					'method' => $listing_obj->get_name(),
					'term' => $term_id
				),
				get_admin_url() . 'upload.php'
			);

			// return the connect link.
			return '<a href="' . esc_url( $url ) . '" style="font-weight: bold">' . __( 'Connect now' ) . '</a>';
		}

		// return the content.
		return $content;
	}

	/**
	 * Set actions for each entry in table.
	 *
	 * @param array   $actions List of actions.
	 * @param WP_Term $term The term.
	 *
	 * @return array
	 */
	public function set_actions( array $actions, WP_Term $term ): array {
		$new_actions = array();
		$new_actions['edit'] = $actions['edit'];
		$new_actions['delete'] = $actions['delete'];

		// get the type name.
		$type = get_term_meta( $term->term_id, 'type', true );

		// get the listing object by this name.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// bail if no object could be found.
		if( ! $listing_obj ) {
			return $new_actions;
		}

		// create URL to connect via click.
		$url = add_query_arg(
			array(
				'page' => Init::get_instance()->get_menu_slug(),
				'method' => $listing_obj->get_name(),
				'term' => $term->term_id
			),
			get_admin_url() . 'upload.php'
		);

		// add connect action.
		$new_actions['connect'] = '<a href="' . esc_url( $url ) . '" style="font-weight: bold">' . __( 'Connect now' ) . '</a>';

		// return resulting list of actions.
		return $new_actions;
	}

	/**
	 * Show our own columns.
	 *
	 * @param array $columns List of columns.
	 *
	 * @return array
	 */
	public function set_table_columns( array $columns ): array {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['name'] = $columns['name'];
		$new_columns['type'] = __( 'Type' );
		$new_columns['connect'] = __( 'Connect' );
		return $new_columns;
	}

	/**
	 * Add term in this taxonomy. Checks also if a term with the given directory does not exist.
	 *
	 * @param string $type The listing type name.
	 * @param string $directory The directory.
	 * @param string $login The login.
	 * @param string $password The password.
	 * @param string $api_key The API key.
	 *
	 * @return void
	 */
	public function add( string $type, string $directory, string $login, string $password, string $api_key ): void {
		// create slug with type and directory.
		$slug = md5( $type . $directory );

		// get term with given directory.
		$term = get_term_by( 'slug', $slug, $this->get_name() );

		// bail if term does exist.
		if( $term instanceof WP_Term ) {
			return;
		}

		// add entry.
		$term = wp_insert_term( $directory, $this->get_name(), array( 'slug' => $slug ) );

		// bail on any error.
		if( is_wp_error( $term ) ) {
			return;
		}

		// add the credentials.
		add_term_meta( $term['term_id'], 'type', $type );
		add_term_meta( $term['term_id'], 'login', Crypt::get_instance()->encrypt( $login ) );
		add_term_meta( $term['term_id'], 'password', Crypt::get_instance()->encrypt( $password ) );
		add_term_meta( $term['term_id'], 'api_key', Crypt::get_instance()->encrypt( $api_key ) );
	}

	/**
	 * Return the saved credentials from single entry.
	 *
	 * @param int $term_id The ID of the term.
	 *
	 * @return array
	 */
	public function get_entry( int $term_id ): array {
		// get the term.
		$term = get_term( $term_id, $this->get_name() );

		// bail if term could not be loaded.
		if( ! $term instanceof WP_Term ) {
			return array();
		}

		// return the data.
		return array(
			'directory' => $term->name,
			'login' => Crypt::get_instance()->decrypt( get_term_meta( $term_id, 'login', true ) ),
			'password' => Crypt::get_instance()->decrypt( get_term_meta( $term_id, 'password', true ) ),
			'api_key' => Crypt::get_instance()->decrypt( get_term_meta( $term_id, 'api_key', true ) ),
		);
	}

	/**
	 * Add our additional fields in the edit screen.
	 *
	 * @param WP_Term $term The term.
	 *
	 * @return void
	 */
	public function set_fields( WP_Term $term ): void {
		// get the values.
		$type = get_term_meta( $term->term_id, 'type', true );
		$login = Crypt::get_instance()->decrypt( get_term_meta( $term->term_id, 'login', true ) );
		$password = Crypt::get_instance()->decrypt( get_term_meta( $term->term_id, 'password', true ) );
		$api_key = Crypt::get_instance()->decrypt( get_term_meta( $term->term_id, 'api_key', true ) );

		// get the type object.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// if no object could be found, show hint.
		if( ! $listing_obj ) {
			?>
			<tr class="form-field">
				<th scope="row"><label for="edlfw-type"><?php echo esc_html__( 'Type' ); ?></label></th>
				<td>
					<p><strong><?php echo esc_html__( 'Type could not be loaded!' ); ?></strong></p>
				</td>
			</tr>
			<?php
			return;
		}

		// output.
		?>
		<tr class="form-field">
			<th scope="row"><label for="edlfw-type"><?php echo esc_html__( 'Type' ); ?></label></th>
			<td>
				<?php echo esc_html( $listing_obj->get_label() ); ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="edlfw-login"><?php echo esc_html__( 'Login' ); ?></label></th>
			<td>
				<input type="text" id="edlfw-login" name="login" value="<?php echo esc_attr( $login ); ?>">
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="edlfw-password"><?php echo esc_html__( 'Password' ); ?></label></th>
			<td>
				<input type="password" id="edlfw-password" name="password" value="<?php echo esc_attr( $password ); ?>">
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="edlfw-api_key"><?php echo esc_html__( 'API Key' ); ?></label></th>
			<td>
				<input type="password" id="edlfw-api_key" name="api_key" value="<?php echo esc_attr( $api_key ); ?>">
			</td>
		</tr>
		<?php
	}

	/**
	 * Save settings from custom taxonomy-fields.
	 *
	 * @param int    $term_id The ID of the term.
	 * @param int    $tt_id The taxonomy-ID of the term.
	 * @param string $taxonomy The name of the taxonomy.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 **/
	public function save_fields( int $term_id, int $tt_id = 0, string $taxonomy = '' ): void {
		// bail if this is not our taxonomy.
		if ( $this->get_name() !== $taxonomy ) {
			return;
		}

		// update the credentials.
		update_term_meta( $term_id, 'login', Crypt::get_instance()->encrypt( (string)filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) );
		update_term_meta( $term_id, 'password', Crypt::get_instance()->encrypt( (string)filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) );
		update_term_meta( $term_id, 'api_key', Crypt::get_instance()->encrypt( (string)filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) );
	}

	/**
	 * Run this on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// get all terms with direct db access.
		$terms = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT ' . $wpdb->terms . '.term_id
                    FROM ' . $wpdb->terms . '
                    INNER JOIN
                        ' . $wpdb->term_taxonomy . '
                        ON
                         ' . $wpdb->term_taxonomy . '.term_id = ' . $wpdb->terms . '.term_id
                    WHERE ' . $wpdb->term_taxonomy . '.taxonomy = %s',
				array( $this->get_name() )
			)
		);

		// delete them.
		foreach ( $terms as $term ) {
			$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->terms,
				array(
					'term_id' => $term->term_id,
				)
			);
		}

		// delete all taxonomy-entries.
		$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $this->get_name() ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// cleanup options.
		delete_option( $this->get_name() . '_children' );
	}
}
