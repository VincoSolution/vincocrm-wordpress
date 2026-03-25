<?php
/**
 * Form builder admin page template.
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vincocrm-wrap">
    <h1>
        <?php esc_html_e( 'VincoCRM Forms', 'vincocrm' ); ?>
        <button type="button" class="page-title-action" id="vincocrm-new-form"><?php esc_html_e( 'Add New Form', 'vincocrm' ); ?></button>
    </h1>

    <div class="vincocrm-forms-wrap">
        <!-- Sidebar: Form list -->
        <div class="vincocrm-forms-sidebar">
            <div class="vincocrm-card">
                <h3><?php esc_html_e( 'Your Forms', 'vincocrm' ); ?></h3>
                <div id="vincocrm-form-list">
                    <p class="description"><?php esc_html_e( 'Loading...', 'vincocrm' ); ?></p>
                </div>
            </div>

            <!-- Field palette -->
            <div class="vincocrm-card" id="vincocrm-field-palette-card">
                <h3><?php esc_html_e( 'Add Fields', 'vincocrm' ); ?></h3>
                <div class="vincocrm-field-palette" id="vincocrm-field-palette">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Main: Form editor -->
        <div class="vincocrm-forms-main">
            <!-- Empty state -->
            <div id="vincocrm-form-empty" class="vincocrm-card" style="text-align:center; padding:48px;">
                <p><?php esc_html_e( 'Select a form from the sidebar or create a new one.', 'vincocrm' ); ?></p>
            </div>

            <!-- Editor (hidden by default) -->
            <div id="vincocrm-form-editor" style="display:none;">
                <div class="vincocrm-card">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <input type="text" id="vincocrm-form-title" class="regular-text"
                               placeholder="<?php esc_attr_e( 'Form Title', 'vincocrm' ); ?>" style="flex:1; font-size:16px; font-weight:600;">
                        <button type="button" class="button button-primary" id="vincocrm-save-form"><?php esc_html_e( 'Save Form', 'vincocrm' ); ?></button>
                        <button type="button" class="button" id="vincocrm-duplicate-form" title="<?php esc_attr_e( 'Duplicate', 'vincocrm' ); ?>">⧉</button>
                        <button type="button" class="button button-link-delete" id="vincocrm-delete-form" title="<?php esc_attr_e( 'Delete', 'vincocrm' ); ?>">✕</button>
                    </div>

                    <!-- Shortcode display -->
                    <div id="vincocrm-form-shortcode" class="vincocrm-badge vincocrm-badge--muted" style="display:none; margin-bottom:16px; font-family:monospace;"></div>

                    <h3><?php esc_html_e( 'Form Fields', 'vincocrm' ); ?></h3>

                    <!-- Builder canvas -->
                    <div class="vincocrm-builder-canvas" id="vincocrm-builder-canvas"
                         data-placeholder="<?php esc_attr_e( 'Drag fields here to build your form', 'vincocrm' ); ?>">
                        <!-- Fields rendered by JS -->
                    </div>
                </div>

                <!-- Live preview -->
                <div class="vincocrm-card">
                    <h3><?php esc_html_e( 'Preview', 'vincocrm' ); ?></h3>
                    <div class="vincocrm-form-preview" id="vincocrm-form-preview">
                        <!-- Preview rendered by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
