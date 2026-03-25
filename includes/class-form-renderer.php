<?php
/**
 * Frontend form renderer — shortcode and AJAX submission.
 *
 * Renders forms via [vincocrm_form id="..."] shortcode.
 * Submits to CRM /api/contact endpoint via AJAX (no page reload).
 * Spam protection: honeypot, timestamp, rate limit, nonce.
 * No jQuery on frontend — vanilla JS only.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Form_Renderer {

    private Api_Client $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function init(): void {
        add_shortcode( 'vincocrm_form', [ $this, 'render_shortcode' ] );
        add_action( 'wp_ajax_vincocrm_submit_form', [ $this, 'ajax_submit' ] );
        add_action( 'wp_ajax_nopriv_vincocrm_submit_form', [ $this, 'ajax_submit' ] );
    }

    /**
     * Render the form shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ): string {
        $atts = shortcode_atts( [
            'id'    => '',
            'class' => '',
        ], $atts, 'vincocrm_form' );

        $form_id = sanitize_key( $atts['id'] );
        if ( empty( $form_id ) ) {
            return '<!-- VincoCRM: Missing form ID -->';
        }

        $form = Form_Builder::get_form( $form_id );
        if ( ! $form || empty( $form['fields'] ) ) {
            return '<!-- VincoCRM: Form not found -->';
        }

        // Enqueue frontend assets.
        $this->enqueue_frontend_assets();

        $fields = $form['fields'];
        $nonce  = wp_create_nonce( 'vincocrm_form_' . $form_id );
        $extra_class = sanitize_html_class( $atts['class'] );

        ob_start();
        ?>
        <form class="vincocrm-contact-form <?php echo esc_attr( $extra_class ); ?>"
              data-form-id="<?php echo esc_attr( $form_id ); ?>"
              data-nonce="<?php echo esc_attr( $nonce ); ?>"
              novalidate>

            <?php foreach ( $fields as $index => $field ) : ?>
                <?php echo $this->render_field( $field, $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>

            <!-- Honeypot (hidden from users) -->
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <input type="text" name="vincocrm_hp" tabindex="-1" autocomplete="off" value="">
            </div>

            <!-- Timestamp (spam check) -->
            <input type="hidden" name="vincocrm_ts" value="<?php echo esc_attr( (string) time() ); ?>">

            <div class="vincocrm-form-footer">
                <button type="submit" class="vincocrm-form-submit">
                    <?php esc_html_e( 'Send Message', 'vincocrm' ); ?>
                </button>
            </div>

            <div class="vincocrm-form-message" role="alert" aria-live="polite"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single form field.
     *
     * @param array $field Field config.
     * @param int   $index Field index.
     * @return string HTML.
     */
    private function render_field( array $field, int $index ): string {
        $type        = $field['type'] ?? 'text';
        $label       = esc_html( $field['label'] ?? '' );
        $placeholder = esc_attr( $field['placeholder'] ?? '' );
        $required    = ! empty( $field['required'] );
        $crm_field   = esc_attr( $field['crm_field'] ?? '' );
        $width       = ( $field['width'] ?? 'full' ) === 'half' ? 'half' : 'full';
        $name        = 'vincocrm_field_' . $index;
        $req_attr    = $required ? 'required' : '';
        $req_marker  = $required ? ' <span class="vincocrm-required">*</span>' : '';

        $html = '<div class="vincocrm-form-field vincocrm-form-field--' . esc_attr( $width ) . '">';
        $html .= '<label for="' . esc_attr( $name ) . '">' . $label . $req_marker . '</label>';

        switch ( $type ) {
            case 'textarea':
                $html .= sprintf(
                    '<textarea id="%s" name="%s" placeholder="%s" rows="4" data-crm-field="%s" %s></textarea>',
                    esc_attr( $name ),
                    esc_attr( $name ),
                    $placeholder,
                    $crm_field,
                    $req_attr
                );
                break;

            case 'select':
                $options = $field['options'] ?? [];
                $html .= sprintf( '<select id="%s" name="%s" data-crm-field="%s" %s>', esc_attr( $name ), esc_attr( $name ), $crm_field, $req_attr );
                $html .= '<option value="">' . esc_html__( '— Select —', 'vincocrm' ) . '</option>';
                foreach ( $options as $opt ) {
                    $html .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'checkbox':
                $html .= sprintf(
                    '<label class="vincocrm-checkbox"><input type="checkbox" id="%s" name="%s" data-crm-field="%s" %s> %s</label>',
                    esc_attr( $name ),
                    esc_attr( $name ),
                    $crm_field,
                    $req_attr,
                    $placeholder
                );
                break;

            case 'email':
                $html .= sprintf(
                    '<input type="email" id="%s" name="%s" placeholder="%s" data-crm-field="%s" %s>',
                    esc_attr( $name ),
                    esc_attr( $name ),
                    $placeholder,
                    $crm_field,
                    $req_attr
                );
                break;

            case 'phone':
                $html .= sprintf(
                    '<input type="tel" id="%s" name="%s" placeholder="%s" data-crm-field="%s" %s>',
                    esc_attr( $name ),
                    esc_attr( $name ),
                    $placeholder,
                    $crm_field,
                    $req_attr
                );
                break;

            default: // text
                $html .= sprintf(
                    '<input type="text" id="%s" name="%s" placeholder="%s" data-crm-field="%s" %s>',
                    esc_attr( $name ),
                    esc_attr( $name ),
                    $placeholder,
                    $crm_field,
                    $req_attr
                );
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Enqueue frontend form CSS and JS.
     */
    private function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'vincocrm-form',
            VINCOCRM_PLUGIN_URL . 'public/css/form.css',
            [],
            VINCOCRM_VERSION
        );

        wp_enqueue_script(
            'vincocrm-form',
            VINCOCRM_PLUGIN_URL . 'public/js/form.js',
            [],
            VINCOCRM_VERSION,
            true
        );

        wp_localize_script( 'vincocrm-form', 'vincoCRMForm', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'strings' => [
                'sending' => __( 'Sending...', 'vincocrm' ),
                'sent'    => __( 'Message sent! We\'ll get back to you shortly.', 'vincocrm' ),
                'error'   => __( 'Something went wrong. Please try again.', 'vincocrm' ),
                'spam'    => __( 'Submission blocked. Please try again later.', 'vincocrm' ),
            ],
        ] );
    }

    /**
     * AJAX: Handle form submission.
     */
    public function ajax_submit(): void {
        $form_id = sanitize_key( $_POST['form_id'] ?? '' );

        // Verify nonce.
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'vincocrm_form_' . $form_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh and try again.', 'vincocrm' ) ] );
        }

        // Honeypot check.
        if ( ! empty( $_POST['vincocrm_hp'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Submission blocked.', 'vincocrm' ) ] );
        }

        // Timestamp check (must be at least 2 seconds since render).
        $ts = intval( $_POST['vincocrm_ts'] ?? 0 );
        if ( $ts > 0 && ( time() - $ts ) < 2 ) {
            wp_send_json_error( [ 'message' => __( 'Please wait a moment before submitting.', 'vincocrm' ) ] );
        }

        // Rate limit (5 submissions per minute per IP).
        $ip       = $this->get_client_ip();
        $rate_key = 'vincocrm_form_rate_' . md5( $ip );
        $count    = (int) get_transient( $rate_key );
        if ( $count >= 5 ) {
            wp_send_json_error( [ 'message' => __( 'Too many submissions. Please try again later.', 'vincocrm' ) ] );
        }
        set_transient( $rate_key, $count + 1, 60 );

        // Load form config.
        $form = Form_Builder::get_form( $form_id );
        if ( ! $form || empty( $form['fields'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Form not found.', 'vincocrm' ) ] );
        }

        // Collect and validate field values.
        $crm_data = [];
        foreach ( $form['fields'] as $index => $field ) {
            $name  = 'vincocrm_field_' . $index;
            $value = sanitize_text_field( wp_unslash( $_POST[ $name ] ?? '' ) );

            // Required check.
            if ( ! empty( $field['required'] ) && empty( $value ) ) {
                wp_send_json_error( [
                    'message' => sprintf(
                        /* translators: %s: field label */
                        __( '%s is required.', 'vincocrm' ),
                        $field['label']
                    ),
                ] );
            }

            // Email validation.
            if ( 'email' === $field['type'] && ! empty( $value ) && ! is_email( $value ) ) {
                wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'vincocrm' ) ] );
            }

            // Map to CRM field.
            $crm_field = $field['crm_field'] ?? '';
            if ( ! empty( $crm_field ) && ! empty( $value ) ) {
                $crm_data[ $crm_field ] = $value;
            }
        }

        // Submit to CRM.
        $result = $this->api->submit_contact_form( [
            'name'    => $crm_data['name'] ?? '',
            'email'   => $crm_data['email'] ?? '',
            'subject' => $crm_data['subject'] ?? '',
            'message' => $crm_data['message'] ?? '',
        ] );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => __( 'Message sent! We\'ll get back to you shortly.', 'vincocrm' ),
            ] );
        }

        wp_send_json_error( [
            'message' => $result['error'] ?? __( 'Something went wrong. Please try again.', 'vincocrm' ),
        ] );
    }

    /**
     * Get client IP (respects proxies).
     */
    private function get_client_ip(): string {
        $headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }
}
