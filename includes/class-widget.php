<?php
/**
 * Widget injection on the frontend.
 *
 * Injects the VincoCRM chat widget script using wp_enqueue_script with
 * script_loader_tag filter for data attributes. Compatible with WP 6.7+
 * script strategies (defer/async).
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Widget {

    private Api_Client $api;
    private string $widget_api_key = '';
    private string $widget_api_url = '';

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_widget' ] );
        add_filter( 'script_loader_tag', [ $this, 'add_widget_data_attributes' ], 10, 3 );
        add_action( 'wp_footer', [ $this, 'render_noscript_fallback' ] );
    }

    /**
     * Conditionally enqueue the widget script.
     */
    public function maybe_enqueue_widget(): void {
        if ( is_admin() ) {
            return;
        }

        $settings = get_option( 'vincocrm_widget_settings', [] );

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        $this->widget_api_key = get_option( 'vincocrm_widget_api_key', '' );
        $this->widget_api_url = get_option( 'vincocrm_widget_api_url', '' );

        if ( empty( $this->widget_api_key ) || empty( $this->widget_api_url ) ) {
            return;
        }

        if ( ! $this->should_show( $settings ) ) {
            return;
        }

        $script_url = rtrim( $this->widget_api_url, '/' ) . '/widget/vincocrm-widget.js';

        wp_enqueue_script(
            'vincocrm-widget',
            $script_url,
            [],
            null, // Versioned by CRM server ETag.
            [
                'strategy'  => 'defer',
                'in_footer' => true,
            ]
        );
    }

    /**
     * Add data-api-key and data-api-url attributes to the widget script tag.
     *
     * Uses the script_loader_tag filter (WP 4.1+).
     *
     * @param string $tag    Script HTML tag.
     * @param string $handle Script handle.
     * @param string $src    Script source URL.
     * @return string Modified tag.
     */
    public function add_widget_data_attributes( string $tag, string $handle, string $src ): string {
        if ( 'vincocrm-widget' !== $handle ) {
            return $tag;
        }

        $api_key = esc_attr( $this->widget_api_key );
        $api_url = esc_attr( $this->widget_api_url );

        // Insert data attributes before the closing >.
        $tag = str_replace(
            ' src=',
            sprintf( ' data-api-key="%s" data-api-url="%s" src=', $api_key, $api_url ),
            $tag
        );

        return $tag;
    }

    /**
     * Render noscript fallback link.
     */
    public function render_noscript_fallback(): void {
        if ( ! wp_script_is( 'vincocrm-widget', 'enqueued' ) ) {
            return;
        }

        $fallback_url = get_option( 'vincocrm_api_url', '' );
        if ( empty( $fallback_url ) ) {
            return;
        }

        printf(
            '<noscript><a href="%s" target="_blank" rel="noopener">%s</a></noscript>' . "\n",
            esc_url( $fallback_url . '/portal' ),
            esc_html__( 'Contact Support', 'vincocrm' )
        );
    }

    /**
     * Determine whether the widget should be shown on the current page.
     *
     * @param array $settings Widget display settings.
     */
    private function should_show( array $settings ): bool {
        $display = $settings['display'] ?? 'all';

        switch ( $display ) {
            case 'all':
                return true;

            case 'include':
                $pages = $settings['include_pages'] ?? [];
                if ( empty( $pages ) ) {
                    return false;
                }
                return is_page( $pages ) || is_single( $pages );

            case 'exclude':
                $pages = $settings['exclude_pages'] ?? [];
                if ( empty( $pages ) ) {
                    return true;
                }
                return ! is_page( $pages ) && ! is_single( $pages );

            case 'woocommerce':
                if ( ! function_exists( 'is_woocommerce' ) ) {
                    return false;
                }
                return is_woocommerce() || is_cart() || is_checkout() || is_account_page();

            default:
                return true;
        }
    }
}
