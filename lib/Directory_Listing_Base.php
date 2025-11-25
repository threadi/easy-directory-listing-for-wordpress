<?php
/**
 * File to handle directory listing methods as base-object.
 *
 * @package easy-directory-listing-for-wordpress
 */

namespace easyDirectoryListingForWordPress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;

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
     * The public description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The public form title.
     *
     * @var string
     */
    protected string $form_title = '';

    /**
     * The public form description.
     *
     * @var string
     */
    protected string $form_description = '';

    /**
     * List of fields to request for the listing.
     *
     * @var bool
     */
    protected array $fields = array();

    /**
     * The directory.
     *
     * @var string
     */
    protected string $directory = '';

    /**
     * List of global actions for this listing object.
     *
     * @var array<int,array<string,string>>
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
     * @var array<int,WP_Error>
     */
    protected array $errors = array();

    /**
     * List of actions for each file.
     *
     * @var array<int,array<string,string>>
     */
    protected array $actions = array();

    /**
     * Marker for export files.
     *
     * @var bool
     */
    protected bool $export_files = false;

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
        return $this->directory;
    }

    /**
     * Set the directory to use.
     *
     * @return void
     */
    public function set_directory( string $directory ): void {
        $this->directory = $directory;
    }

    /**
     * Return list of fields we need for this listing.
     *
     * @return array<string,array<string,mixed>>
     */
    public function get_fields(): array {
        return $this->fields;
    }

    /**
     * Set the fields we need for this listing.
     *
     * @param array<string,array<string,mixed>> $fields
     *
     * @return void
     */
    public function set_fields( array $fields ): void {
        $this->fields = $fields;
    }

    /**
     * Return the directory listing structure.
     *
     * @param string $directory The requested directory.
     *
     * @return array<int|string,mixed>
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
     * @return array<int,array<string,string>>
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
     * Return the form title.
     *
     * @return string
     */
    public function get_form_title(): string {
        return $this->form_title;
    }

    /**
     * Return config for display of listing in backend.
     *
     * @return array<string,mixed>
     */
    public function get_config(): array {
        return array(
            'form_title'                    => $this->get_form_title(),
            'form_description'              => $this->get_form_description(),
            'directory'                => $this->get_directory(),
            'listing_base_object_name' => $this->get_name(),
            'fields' => $this->get_fields(),
            'nonce'                    => wp_create_nonce( $this->get_nonce_name() ),
            'actions'                  => $this->get_actions(),
            'global_actions'           => $this->get_global_actions(),
            'archive'                  => Init::get_instance()->is_archive_enabled(),
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
     * @return array<int,array<string,string>>
     */
    protected function get_global_actions(): array {
        if ( empty( $this->global_actions ) ) {
            $this->global_actions = array(
                array(
                    'action' => 'setTree( false );setLoadTree( ! loadTree );',
                    'label'  => Init::get_instance()->get_translations()['reload'],
                ),
            );
        }
        return $this->global_actions;
    }

    /**
     * Add single global action.
     *
     * @param array<int,array<string,string>> $global_action Single global action to add.
     *
     * @return void
     */
    public function add_global_action( array $global_action ): void {
        $this->global_actions = array_merge( $this->get_global_actions(), $global_action );
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
     * @return array<int,WP_Error>
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Return whether this listing object is disabled.
     *
     * @return bool
     */
    public function is_disabled(): bool {
        return false;
    }

    /**
     * Return the description for this listing object.
     *
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Return the description for this listing object.
     *
     * @return string
     */
    public function get_form_description(): string {
        return $this->form_description;
    }

    /**
     * Set actions.
     *
     * @param array<int,array<string,string>> $actions List of actions for each file.
     *
     * @return void
     */
    public function set_actions( array $actions ): void {
        $this->actions = $actions;
    }

    /**
     * Return the URL. Possibility to complete it depending on listing method.
     *
     * @param string $url The given URL.
     *
     * @return string
     */
    public function get_url( string $url ): string {
        return $url;
    }

    /**
     * Return a custom view URL.
     *
     * @return string
     */
    public function get_view_url(): string {
        return '';
    }

    /**
     * Return list of translations.
     *
     * @param array<string,mixed> $translations List of translations.
     *
     * @return array<string,mixed>
     */
    public function get_translations( array $translations ): array {
        return $translations;
    }

    /**
     * Return whether this listing could also be used to export files.
     *
     * @return bool
     */
    public function can_export_files(): bool {
        return $this->export_files;
    }

    /**
     * Export a file to this service. Returns true if it was successfully.
     *
     * @param int $attachment_id The attachment ID.
     * @param string $target The target.
     * @param array $credentials The credentials.
     * @return bool
     */
    public function export_file( int $attachment_id, string $target, array $credentials ): bool {
        return false;
    }

    /**
     * Delete an exported file.
     *
     * @param string $url The given URL to delete.
     * @param array $credentials The credentials.
     * @return bool
     */
    public function delete_exported_file( string $url, array $credentials ): bool {
        return false;
    }
}
