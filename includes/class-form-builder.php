<?php
/**
 * Form builder admin — Classic and Modern engines.
 *
 * Both engines save identical JSON schema. Switch between engines at any time.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Form_Builder {

    private Api_Client $api;

    /**
     * Default field types available in the builder.
     */
    private const FIELD_TYPES = [
        'text'     => [ 'label' => 'Text', 'icon' => 'T' ],
        'email'    => [ 'label' => 'Email', 'icon' => '@' ],
        'phone'    => [ 'label' => 'Phone', 'icon' => '#' ],
        'textarea' => [ 'label' => 'Textarea', 'icon' => '¶' ],
        'select'   => [ 'label' => 'Dropdown', 'icon' => '▼' ],
        'checkbox' => [ 'label' => 'Checkbox', 'icon' => '☑' ],
    ];

    /**
     * CRM field mappings.
     */
    private const CRM_MAPPINGS = [
        ''        => '— Not mapped —',
        'name'    => 'Contact Name',
        'email'   => 'Contact Email',
        'phone'   => 'Contact Phone',
        'subject' => 'Subject',
        'message' => 'Message',
    ];

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function init(): void {
        add_action( 'vincocrm_render_forms_page', [ $this, 'render_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_builder_assets' ] );

        // AJAX handlers.
        add_action( 'wp_ajax_vincocrm_save_form', [ $this, 'ajax_save_form' ] );
        add_action( 'wp_ajax_vincocrm_delete_form', [ $this, 'ajax_delete_form' ] );
        add_action( 'wp_ajax_vincocrm_duplicate_form', [ $this, 'ajax_duplicate_form' ] );
        add_action( 'wp_ajax_vincocrm_list_forms', [ $this, 'ajax_list_forms' ] );
        add_action( 'wp_ajax_vincocrm_get_form', [ $this, 'ajax_get_form' ] );
    }

    /**
     * Enqueue form builder JS/CSS on the forms page.
     */
    public function enqueue_builder_assets( string $hook ): void {
        if ( 'vincocrm_page_vincocrm-forms' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'vincocrm-form-builder',
            VINCOCRM_PLUGIN_URL . 'admin/js/form-builder.js',
            [ 'vincocrm-admin' ],
            VINCOCRM_VERSION,
            true
        );

        wp_localize_script( 'vincocrm-form-builder', 'vincoCRMForms', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'vincocrm_admin' ),
            'fieldTypes'  => self::FIELD_TYPES,
            'crmMappings' => self::CRM_MAPPINGS,
            'engine'      => $this->get_engine(),
            'strings'     => [
                'formSaved'     => __( 'Form saved!', 'vincocrm' ),
                'formDeleted'   => __( 'Form deleted.', 'vincocrm' ),
                'confirmDelete' => __( 'Are you sure you want to delete this form?', 'vincocrm' ),
                'dragHere'      => __( 'Drag fields here to build your form', 'vincocrm' ),
                'untitled'      => __( 'Untitled Form', 'vincocrm' ),
                'shortcodeLabel' => __( 'Shortcode:', 'vincocrm' ),
            ],
        ] );
    }

    /**
     * Render the forms management page.
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include VINCOCRM_PLUGIN_DIR . 'admin/views/form-builder.php';
    }

    /**
     * AJAX: Save a form.
     */
    public function ajax_save_form(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $form_id = sanitize_key( $_POST['form_id'] ?? '' );
        $title   = sanitize_text_field( $_POST['title'] ?? __( 'Untitled Form', 'vincocrm' ) );
        $fields  = $_POST['fields'] ?? '[]'; // JSON string.

        // Validate JSON.
        $decoded = json_decode( wp_unslash( $fields ), true );
        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid form data.', 'vincocrm' ) ] );
        }

        // Sanitize each field.
        $sanitized = $this->sanitize_fields( $decoded );

        // Generate ID if new.
        if ( empty( $form_id ) ) {
            $form_id = 'form_' . wp_generate_password( 8, false );
        }

        $form = [
            'id'         => $form_id,
            'title'      => $title,
            'fields'     => $sanitized,
            'created_at' => get_option( "vincocrm_form_{$form_id}", [] )['created_at'] ?? current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];

        update_option( "vincocrm_form_{$form_id}", $form, false );

        // Track in form index.
        $index = get_option( 'vincocrm_form_index', [] );
        if ( ! in_array( $form_id, $index, true ) ) {
            $index[] = $form_id;
            update_option( 'vincocrm_form_index', $index, false );
        }

        wp_send_json_success( [
            'message'   => __( 'Form saved!', 'vincocrm' ),
            'form'      => $form,
            'shortcode' => '[vincocrm_form id="' . $form_id . '"]',
        ] );
    }

    /**
     * AJAX: Delete a form.
     */
    public function ajax_delete_form(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $form_id = sanitize_key( $_POST['form_id'] ?? '' );
        if ( empty( $form_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid form ID.', 'vincocrm' ) ] );
        }

        delete_option( "vincocrm_form_{$form_id}" );

        $index = get_option( 'vincocrm_form_index', [] );
        $index = array_values( array_diff( $index, [ $form_id ] ) );
        update_option( 'vincocrm_form_index', $index, false );

        wp_send_json_success( [ 'message' => __( 'Form deleted.', 'vincocrm' ) ] );
    }

    /**
     * AJAX: Duplicate a form.
     */
    public function ajax_duplicate_form(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $form_id = sanitize_key( $_POST['form_id'] ?? '' );
        $form    = get_option( "vincocrm_form_{$form_id}", null );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Form not found.', 'vincocrm' ) ] );
        }

        $new_id  = 'form_' . wp_generate_password( 8, false );
        $new_form = $form;
        $new_form['id']         = $new_id;
        $new_form['title']      = $form['title'] . ' ' . __( '(Copy)', 'vincocrm' );
        $new_form['created_at'] = current_time( 'mysql' );
        $new_form['updated_at'] = current_time( 'mysql' );

        update_option( "vincocrm_form_{$new_id}", $new_form, false );

        $index   = get_option( 'vincocrm_form_index', [] );
        $index[] = $new_id;
        update_option( 'vincocrm_form_index', $index, false );

        wp_send_json_success( [
            'message' => __( 'Form duplicated!', 'vincocrm' ),
            'form'    => $new_form,
        ] );
    }

    /**
     * AJAX: List all forms.
     */
    public function ajax_list_forms(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $index = get_option( 'vincocrm_form_index', [] );
        $forms = [];

        foreach ( $index as $form_id ) {
            $form = get_option( "vincocrm_form_{$form_id}", null );
            if ( $form ) {
                $forms[] = $form;
            }
        }

        wp_send_json_success( [ 'forms' => $forms ] );
    }

    /**
     * AJAX: Get a single form.
     */
    public function ajax_get_form(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $form_id = sanitize_key( $_POST['form_id'] ?? '' );
        $form    = get_option( "vincocrm_form_{$form_id}", null );

        if ( ! $form ) {
            wp_send_json_error( [ 'message' => __( 'Form not found.', 'vincocrm' ) ] );
        }

        wp_send_json_success( [ 'form' => $form ] );
    }

    /**
     * Get the active builder engine.
     */
    private function get_engine(): string {
        $settings = get_option( 'vincocrm_form_settings', [] );
        return $settings['engine'] ?? 'classic';
    }

    /**
     * Sanitize form field definitions.
     *
     * @param array $fields Raw field array.
     * @return array Sanitized fields.
     */
    private function sanitize_fields( array $fields ): array {
        $sanitized = [];
        $allowed_types = array_keys( self::FIELD_TYPES );

        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $type = sanitize_key( $field['type'] ?? 'text' );
            if ( ! in_array( $type, $allowed_types, true ) ) {
                continue;
            }

            $sanitized[] = [
                'type'        => $type,
                'label'       => sanitize_text_field( $field['label'] ?? '' ),
                'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
                'required'    => ! empty( $field['required'] ),
                'crm_field'   => sanitize_key( $field['crm_field'] ?? '' ),
                'options'     => isset( $field['options'] ) && is_array( $field['options'] )
                    ? array_map( 'sanitize_text_field', $field['options'] )
                    : [],
                'width'       => in_array( $field['width'] ?? 'full', [ 'full', 'half' ], true )
                    ? $field['width']
                    : 'full',
            ];
        }

        return $sanitized;
    }

    /**
     * Get a form by ID (public helper for renderer).
     *
     * @param string $form_id
     * @return array|null
     */
    public static function get_form( string $form_id ): ?array {
        $form = get_option( "vincocrm_form_{$form_id}", null );
        return is_array( $form ) ? $form : null;
    }

    /**
     * Get default form schema (for new forms).
     */
    public static function get_default_fields(): array {
        return [
            [
                'type'        => 'text',
                'label'       => 'Name',
                'placeholder' => 'Your name',
                'required'    => true,
                'crm_field'   => 'name',
                'options'     => [],
                'width'       => 'full',
            ],
            [
                'type'        => 'email',
                'label'       => 'Email',
                'placeholder' => 'your@email.com',
                'required'    => true,
                'crm_field'   => 'email',
                'options'     => [],
                'width'       => 'full',
            ],
            [
                'type'        => 'text',
                'label'       => 'Subject',
                'placeholder' => 'How can we help?',
                'required'    => false,
                'crm_field'   => 'subject',
                'options'     => [],
                'width'       => 'full',
            ],
            [
                'type'        => 'textarea',
                'label'       => 'Message',
                'placeholder' => 'Tell us more...',
                'required'    => true,
                'crm_field'   => 'message',
                'options'     => [],
                'width'       => 'full',
            ],
        ];
    }
}
