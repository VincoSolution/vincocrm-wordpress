<?php
/**
 * VincoCRM Uninstall
 *
 * Removes all plugin data when the plugin is deleted via WP admin.
 *
 * @package VincoCRM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all plugin options.
$options = [
    'vincocrm_version',
    'vincocrm_setup_complete',
    'vincocrm_api_url',
    'vincocrm_encrypted_tokens',
    'vincocrm_workspace_id',
    'vincocrm_workspace_name',
    'vincocrm_widget_api_key',
    'vincocrm_widget_api_url',
    'vincocrm_widget_config',
    'vincocrm_widget_settings',
    'vincocrm_contact_sync_settings',
    'vincocrm_order_sync_settings',
    'vincocrm_form_settings',
    'vincocrm_retry_queue',
    'vincocrm_last_order_sync',
    'vincocrm_wc_connected',
    'vincocrm_wc_store_id',
    'vincocrm_user_email',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove all saved forms.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        'vincocrm_form_%'
    )
);

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'vincocrm_retry_queue' );
wp_clear_scheduled_hook( 'vincocrm_order_sync' );

// Remove transients.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_vincocrm_%',
        '_transient_timeout_vincocrm_%'
    )
);
