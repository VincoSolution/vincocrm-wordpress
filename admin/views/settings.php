<?php
/**
 * Admin settings page template.
 *
 * @package VincoCRM
 * @var string $active_tab Current active tab.
 * @var array  $tabs       Available tabs.
 */

defined( 'ABSPATH' ) || exit;

$widget_settings       = get_option( 'vincocrm_widget_settings', [] );
$contact_sync_settings = get_option( 'vincocrm_contact_sync_settings', [] );
$order_sync_settings   = get_option( 'vincocrm_order_sync_settings', [] );
$form_settings         = get_option( 'vincocrm_form_settings', [] );
$is_connected          = $this->api->is_connected();
$workspace_name        = get_option( 'vincocrm_workspace_name', '' );
$user_email            = get_option( 'vincocrm_user_email', '' );
$wc_connected          = get_option( 'vincocrm_wc_connected', false );
$retry_queue           = new \VincoCRM\Retry_Queue( $this->api );
$queue_count           = $retry_queue->count();
?>
<div class="wrap vincocrm-wrap">
    <h1><?php esc_html_e( 'VincoCRM Settings', 'vincocrm' ); ?></h1>

    <?php if ( $is_connected ) : ?>
        <div class="vincocrm-status vincocrm-status--connected">
            <span class="vincocrm-status__dot"></span>
            <?php
            printf(
                /* translators: 1: workspace name, 2: user email */
                esc_html__( 'Connected to %1$s as %2$s', 'vincocrm' ),
                '<strong>' . esc_html( $workspace_name ) . '</strong>',
                esc_html( $user_email )
            );
            ?>
            <button type="button" class="button button-small vincocrm-test-connection"><?php esc_html_e( 'Test', 'vincocrm' ); ?></button>
            <button type="button" class="button button-small button-link-delete vincocrm-disconnect"><?php esc_html_e( 'Disconnect', 'vincocrm' ); ?></button>
        </div>
    <?php else : ?>
        <div class="vincocrm-status vincocrm-status--disconnected">
            <span class="vincocrm-status__dot"></span>
            <?php esc_html_e( 'Not connected.', 'vincocrm' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=vincocrm-setup' ) ); ?>" class="button button-primary button-small">
                <?php esc_html_e( 'Run Setup Wizard', 'vincocrm' ); ?>
            </a>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper vincocrm-tabs">
        <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=vincocrm&tab=' . $tab_key ) ); ?>"
               class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $tab_label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="vincocrm-tab-content">
        <?php if ( 'connection' === $active_tab ) : ?>
            <div class="vincocrm-card">
                <h2><?php esc_html_e( 'Connection Details', 'vincocrm' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'API URL', 'vincocrm' ); ?></th>
                        <td><code><?php echo esc_html( get_option( 'vincocrm_api_url', '—' ) ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Workspace', 'vincocrm' ); ?></th>
                        <td><?php echo esc_html( $workspace_name ?: '—' ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Widget API Key', 'vincocrm' ); ?></th>
                        <td><code><?php echo esc_html( get_option( 'vincocrm_widget_api_key', '—' ) ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'WooCommerce', 'vincocrm' ); ?></th>
                        <td>
                            <?php if ( $wc_connected ) : ?>
                                <span class="vincocrm-badge vincocrm-badge--success"><?php esc_html_e( 'Connected', 'vincocrm' ); ?></span>
                                <?php if ( defined( 'WC_VERSION' ) ) : ?>
                                    <span class="description">(v<?php echo esc_html( WC_VERSION ); ?>, REST API v3)</span>
                                <?php endif; ?>
                            <?php elseif ( class_exists( 'WooCommerce' ) ) : ?>
                                <span class="vincocrm-badge vincocrm-badge--warning"><?php esc_html_e( 'Not connected', 'vincocrm' ); ?></span>
                            <?php else : ?>
                                <span class="vincocrm-badge vincocrm-badge--muted"><?php esc_html_e( 'WooCommerce not installed', 'vincocrm' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

        <?php elseif ( 'widget' === $active_tab ) : ?>
            <form class="vincocrm-settings-form" data-tab="widget">
                <div class="vincocrm-card">
                    <h2><?php esc_html_e( 'Chat Widget', 'vincocrm' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Control where the VincoCRM chat widget appears on your site.', 'vincocrm' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Widget', 'vincocrm' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="widget_enabled" value="1" <?php checked( $widget_settings['enabled'] ?? false ); ?>>
                                    <?php esc_html_e( 'Show chat widget on the frontend', 'vincocrm' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Display On', 'vincocrm' ); ?></th>
                            <td>
                                <select name="widget_display">
                                    <option value="all" <?php selected( $widget_settings['display'] ?? 'all', 'all' ); ?>>
                                        <?php esc_html_e( 'All pages', 'vincocrm' ); ?>
                                    </option>
                                    <option value="include" <?php selected( $widget_settings['display'] ?? '', 'include' ); ?>>
                                        <?php esc_html_e( 'Only specific pages', 'vincocrm' ); ?>
                                    </option>
                                    <option value="exclude" <?php selected( $widget_settings['display'] ?? '', 'exclude' ); ?>>
                                        <?php esc_html_e( 'All pages except...', 'vincocrm' ); ?>
                                    </option>
                                    <option value="woocommerce" <?php selected( $widget_settings['display'] ?? '', 'woocommerce' ); ?>>
                                        <?php esc_html_e( 'WooCommerce pages only', 'vincocrm' ); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr class="vincocrm-conditional" data-show-when="widget_display=include">
                            <th><?php esc_html_e( 'Include Pages', 'vincocrm' ); ?></th>
                            <td>
                                <input type="text" name="widget_include_pages" class="regular-text"
                                       value="<?php echo esc_attr( implode( ',', $widget_settings['include_pages'] ?? [] ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Comma-separated page IDs', 'vincocrm' ); ?>">
                                <p class="description"><?php esc_html_e( 'Enter page/post IDs separated by commas.', 'vincocrm' ); ?></p>
                            </td>
                        </tr>
                        <tr class="vincocrm-conditional" data-show-when="widget_display=exclude">
                            <th><?php esc_html_e( 'Exclude Pages', 'vincocrm' ); ?></th>
                            <td>
                                <input type="text" name="widget_exclude_pages" class="regular-text"
                                       value="<?php echo esc_attr( implode( ',', $widget_settings['exclude_pages'] ?? [] ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Comma-separated page IDs', 'vincocrm' ); ?>">
                                <p class="description"><?php esc_html_e( 'Enter page/post IDs to exclude, separated by commas.', 'vincocrm' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary vincocrm-save-settings"><?php esc_html_e( 'Save Widget Settings', 'vincocrm' ); ?></button>
                </p>
            </form>

        <?php elseif ( 'contact_sync' === $active_tab ) : ?>
            <form class="vincocrm-settings-form" data-tab="contact_sync">
                <div class="vincocrm-card">
                    <h2><?php esc_html_e( 'Contact Sync', 'vincocrm' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Automatically sync WordPress users to VincoCRM contacts.', 'vincocrm' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Sync', 'vincocrm' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="contact_sync_enabled" value="1" <?php checked( $contact_sync_settings['enabled'] ?? true ); ?>>
                                    <?php esc_html_e( 'Sync contacts to VincoCRM', 'vincocrm' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Sync Triggers', 'vincocrm' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="contact_sync_on_register" value="1" <?php checked( $contact_sync_settings['on_register'] ?? true ); ?>>
                                        <?php esc_html_e( 'On user registration', 'vincocrm' ); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="contact_sync_on_login" value="1" <?php checked( $contact_sync_settings['on_login'] ?? false ); ?>>
                                        <?php esc_html_e( 'On user login', 'vincocrm' ); ?>
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="contact_sync_on_checkout" value="1" <?php checked( $contact_sync_settings['on_checkout'] ?? true ); ?>>
                                        <?php esc_html_e( 'On WooCommerce checkout', 'vincocrm' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary vincocrm-save-settings"><?php esc_html_e( 'Save Contact Sync Settings', 'vincocrm' ); ?></button>
                </p>
            </form>

        <?php elseif ( 'order_sync' === $active_tab ) : ?>
            <form class="vincocrm-settings-form" data-tab="order_sync">
                <div class="vincocrm-card">
                    <h2><?php esc_html_e( 'Order Sync', 'vincocrm' ); ?></h2>
                    <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                        <div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is not active. Order sync requires WooCommerce.', 'vincocrm' ); ?></p></div>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable Order Sync', 'vincocrm' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="order_sync_enabled" value="1" <?php checked( $order_sync_settings['enabled'] ?? true ); ?>>
                                    <?php esc_html_e( 'Push orders to VincoCRM', 'vincocrm' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Real-time Sync', 'vincocrm' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="order_sync_realtime" value="1" <?php checked( $order_sync_settings['realtime'] ?? true ); ?>>
                                    <?php esc_html_e( 'Push orders immediately on status change', 'vincocrm' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Batch Size', 'vincocrm' ); ?></th>
                            <td>
                                <input type="number" name="order_sync_batch_size" min="1" max="100" class="small-text"
                                       value="<?php echo esc_attr( $order_sync_settings['batch_size'] ?? 20 ); ?>">
                                <p class="description"><?php esc_html_e( 'Orders per batch for historical sync (1-100).', 'vincocrm' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary vincocrm-save-settings"><?php esc_html_e( 'Save Order Sync Settings', 'vincocrm' ); ?></button>
                </p>
            </form>

        <?php elseif ( 'forms' === $active_tab ) : ?>
            <form class="vincocrm-settings-form" data-tab="forms">
                <div class="vincocrm-card">
                    <h2><?php esc_html_e( 'Form Builder', 'vincocrm' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Choose the form builder engine for your contact forms.', 'vincocrm' ); ?></p>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Builder Engine', 'vincocrm' ); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="form_engine" value="classic" <?php checked( $form_settings['engine'] ?? 'classic', 'classic' ); ?>>
                                        <strong><?php esc_html_e( 'Classic', 'vincocrm' ); ?></strong>
                                        — <?php esc_html_e( 'Simple table-based builder, lightweight and compatible.', 'vincocrm' ); ?>
                                    </label><br><br>
                                    <label>
                                        <input type="radio" name="form_engine" value="modern" <?php checked( $form_settings['engine'] ?? '', 'modern' ); ?>>
                                        <strong><?php esc_html_e( 'Modern', 'vincocrm' ); ?></strong>
                                        — <?php esc_html_e( 'Drag-and-drop visual builder with live preview.', 'vincocrm' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary vincocrm-save-settings"><?php esc_html_e( 'Save Form Settings', 'vincocrm' ); ?></button>
                </p>
            </form>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vincocrm-forms' ) ); ?>" class="button">
                    <?php esc_html_e( 'Manage Forms →', 'vincocrm' ); ?>
                </a>
            </p>

        <?php elseif ( 'advanced' === $active_tab ) : ?>
            <form class="vincocrm-settings-form" data-tab="advanced">
                <div class="vincocrm-card">
                    <h2><?php esc_html_e( 'Advanced Settings', 'vincocrm' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'API URL', 'vincocrm' ); ?></th>
                            <td>
                                <input type="url" name="api_url" class="regular-text"
                                       value="<?php echo esc_attr( get_option( 'vincocrm_api_url', '' ) ); ?>"
                                       placeholder="https://api.vincocrm.com">
                                <p class="description"><?php esc_html_e( 'The base URL of your VincoCRM instance (no trailing slash).', 'vincocrm' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Plugin Version', 'vincocrm' ); ?></th>
                            <td><code><?php echo esc_html( VINCOCRM_VERSION ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Retry Queue', 'vincocrm' ); ?></th>
                            <td>
                                <?php if ( $queue_count > 0 ) : ?>
                                    <span class="vincocrm-badge vincocrm-badge--warning">
                                        <?php
                                        printf(
                                            /* translators: %d: number of items */
                                            esc_html__( '%d pending items', 'vincocrm' ),
                                            $queue_count
                                        );
                                        ?>
                                    </span>
                                    <button type="button" class="button button-small vincocrm-clear-retry-queue"><?php esc_html_e( 'Clear Queue', 'vincocrm' ); ?></button>
                                <?php else : ?>
                                    <span class="vincocrm-badge vincocrm-badge--success"><?php esc_html_e( 'Empty', 'vincocrm' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary vincocrm-save-settings"><?php esc_html_e( 'Save Advanced Settings', 'vincocrm' ); ?></button>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>
