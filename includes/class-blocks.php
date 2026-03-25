<?php
/**
 * Gutenberg block registration for VincoCRM forms.
 *
 * Registers a server-side rendered block that wraps the [vincocrm_form] shortcode.
 * No build step required — uses block.json + PHP render callback.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Blocks {

    public function init(): void {
        add_action( 'init', [ $this, 'register_blocks' ] );
    }

    /**
     * Register the VincoCRM Form block.
     */
    public function register_blocks(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type( 'vincocrm/form', [
            'api_version'     => 3,
            'title'           => __( 'VincoCRM Form', 'vincocrm' ),
            'description'     => __( 'Embed a VincoCRM contact form.', 'vincocrm' ),
            'category'        => 'widgets',
            'icon'            => 'email',
            'keywords'        => [ 'contact', 'form', 'crm', 'vincocrm' ],
            'supports'        => [
                'html'      => false,
                'align'     => [ 'wide', 'full' ],
                'className' => true,
            ],
            'attributes'      => [
                'formId' => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ],
            'editor_script'   => 'vincocrm-block-editor',
            'render_callback' => [ $this, 'render_block' ],
        ] );

        // Editor script (inline, no build step).
        wp_register_script(
            'vincocrm-block-editor',
            false,
            [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ],
            VINCOCRM_VERSION,
            true
        );

        // Inline the block editor JS.
        wp_add_inline_script( 'vincocrm-block-editor', $this->get_editor_script() );
    }

    /**
     * Server-side render callback.
     *
     * @param array $attributes Block attributes.
     * @return string HTML output.
     */
    public function render_block( array $attributes ): string {
        $form_id = sanitize_key( $attributes['formId'] ?? '' );

        if ( empty( $form_id ) ) {
            return '<p>' . esc_html__( 'Please select a VincoCRM form.', 'vincocrm' ) . '</p>';
        }

        return do_shortcode( '[vincocrm_form id="' . $form_id . '"]' );
    }

    /**
     * Generate the Gutenberg block editor script inline.
     *
     * Uses wp.blocks.registerBlockType with a ServerSideRender.
     */
    private function get_editor_script(): string {
        $forms = $this->get_form_options();
        $forms_json = wp_json_encode( $forms );

        return <<<JS
(function(wp) {
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var SelectControl = wp.components.SelectControl;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;
    var Placeholder = wp.components.Placeholder;

    var formOptions = {$forms_json};

    registerBlockType('vincocrm/form', {
        edit: function(props) {
            var formId = props.attributes.formId;

            if (!formId) {
                return el(Placeholder, {
                    icon: 'email',
                    label: 'VincoCRM Form',
                    instructions: 'Select a form to display.',
                },
                    el(SelectControl, {
                        value: formId,
                        options: formOptions,
                        onChange: function(val) { props.setAttributes({ formId: val }); },
                    })
                );
            }

            return el('div', { className: props.className },
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Form Settings' },
                        el(SelectControl, {
                            label: 'Form',
                            value: formId,
                            options: formOptions,
                            onChange: function(val) { props.setAttributes({ formId: val }); },
                        })
                    )
                ),
                ServerSideRender
                    ? el(ServerSideRender, { block: 'vincocrm/form', attributes: props.attributes })
                    : el('p', {}, 'VincoCRM Form: ' + formId)
            );
        },
        save: function() {
            return null;
        },
    });
})(window.wp);
JS;
    }

    /**
     * Get form options for the block editor dropdown.
     *
     * @return array{value: string, label: string}[]
     */
    private function get_form_options(): array {
        $options = [
            [ 'value' => '', 'label' => __( '— Select a form —', 'vincocrm' ) ],
        ];

        $index = get_option( 'vincocrm_form_index', [] );
        foreach ( $index as $form_id ) {
            $form = get_option( "vincocrm_form_{$form_id}" );
            if ( $form ) {
                $options[] = [
                    'value' => $form_id,
                    'label' => $form['title'] ?? $form_id,
                ];
            }
        }

        return $options;
    }
}
